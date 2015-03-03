<?php
namespace Rswork\Silex;

use Silex;
use Rswork;
use Monolog;
use JMS;
use Sorien;
use Silex\Application as BaseApplication;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Doctrine\DBAL\Configuration;

class Application extends BaseApplication
{
    protected $rootDir;
    protected $name = 'Rswork Silex Skeleton';

    public function __construct(array $values = array())
    {
        if( !isset( $values['debug'] ) ) {
            $values['debug'] = false;
        }

        if( isset($values['appname']) ) {
            $this->name = $values['appname'];
        }

        if( !isset($values['locale']) ) {
            $values['locale'] = 'en-US';
        }

        $values['appname'] = $this->name;
        $this->rootDir = $this->getRootDir();
        $values['kernel.root_dir'] = realpath($this->rootDir) ?: $this->rootDir;
        $values['kernel.cache_dir'] = realpath($this->getCacheDir()) ?: $this->getCacheDir();
        $values['kernel.log_dir'] = realpath($this->getLogDir()) ?: $this->getLogDir();
        $values['public_dir'] = $values['kernel.root_dir'] . '/www';

        parent::__construct( $values );

        $app = $this;

        $app['kernel.debug'] = $app['debug'];
        $app['kernel.charset'] = $app['charset'];

        $app['app.config_path'] = realpath($app['kernel.root_dir'] . '/app/config') ?: $app['kernel.root_dir'] . '/app/config';

        if( $app['debug'] ) {
            $app->register(new Silex\Provider\MonologServiceProvider(), array(
                'monolog.logfile' => $app['kernel.log_dir'].'/debug.log',
                'monolog.name' => $app['appname'],
                'monolog.level'   => Monolog\Logger::DEBUG,
            ));
        } else {
            $app->register(new Silex\Provider\MonologServiceProvider(), array(
                'monolog.logfile' => $app['kernel.log_dir'].'/prod.log',
                'monolog.name' => $app['appname'],
                'monolog.level'   => Monolog\Logger::WARNING,
            ));
        }

        /*
         * register controller as service
         */
        $app->register(new Silex\Provider\ServiceControllerServiceProvider());

        /*
         * register session service
         */
        $app->register( new Silex\Provider\SessionServiceProvider() );

        /*
         * register swiftmailer bundle
         */
        $app['mailer'] = $app->share(function ($app) {
            return new \Swift_Mailer($app['swiftmailer.transport']);
        });

        /*
         * register url generator service
         */
        $app->register( new Silex\Provider\UrlGeneratorServiceProvider() );

        /*
         * load config as shared services
         */
        if( $app['debug'] ) {
            $app['config'] = $app->share( function ( Application $app ) {
                return call_user_func( $app->raw('app.yaml.parse'), 'config_dev.yml' );
            } );
        } else {
            $app['config'] = $app->share( function ( Application $app ) {
                return call_user_func( $app->raw('app.yaml.parse'), 'config.yml' );
            } );
        }

        $app['app.yaml.parse'] = $app->share( function($file) use ( $app ) {
            $config = Yaml::parse(file_get_contents($app['app.config_path'].DIRECTORY_SEPARATOR.$file));

            if (is_array($config)) {
                if(isset( $config['imports'])) {
                    foreach ($config['imports'] as $resource) {
                        $new_config = call_user_func( $app->raw('app.yaml.parse'), $resource['resource'] );
                    }
                    unset($config['imports']);
                }

                if (isset($new_config) && is_array($new_config)) {
                    $config = array_replace_recursive($new_config, $config);
                }
            } else {
                $config = array();
            }

            return $config;
        } );

        /*
         * register twig service
         */
        $app->register(new Silex\Provider\TwigServiceProvider(), array(
            'twig.path' => $app['kernel.root_dir'].'/app/views',
            'twig.options' => array(
                'cache' => $app['kernel.cache_dir'].'/twig',
                'autoescape' => false,
            ),
        ));

        /**
         * twig extend
         */
        $app['twig'] = $app->share($app->extend('twig', function($twig, $app) {
            $twig->addFunction(new \Twig_SimpleFunction('asset', function ($asset) use ($app) {
                return sprintf(rtrim(rtrim($app['request']->getBasePath(), 'index.php/'),'index_dev.php')."/%s", ltrim($asset, '/'));
            }));

            return $twig;
        }));

        $app['asset_url'] = $app->share(function( $asset = '' ) use ($app) {
            return sprintf(rtrim(rtrim($app['request']->getBasePath(), 'index.php/'),'index_dev.php')."/%s", ltrim($asset, '/'));
        });

        /**
         * register doctrine dbal service
         */
        if( isset( $app['config'] ) AND isset( $app['config']['dbs'] ) ) {
            $app->register(new Silex\Provider\DoctrineServiceProvider(), array(
                'dbs.options' => $app['config']['dbs'],
            ));
        }

        /**
         * register JMS serializer service
         */
        $app->register(new JMS\SerializerServiceProvider\SerializerServiceProvider(), array(
            'serializer.src_directory' => $app['kernel.root_dir'].'/var/vendor/jms/serializer-bundle',
            'serializer.cache.directory' => $app['kernel.cache_dir'].'/jms',
        ));

        /*
         * register validator
         */
        $app->register( new Silex\Provider\ValidatorServiceProvider() );

        if( $app['debug'] ) {
            $app->register($p = new Silex\Provider\WebProfilerServiceProvider(), array(
                'profiler.cache_dir' => $app['kernel.cache_dir'].'/profiler',
                'profiler.mount_prefix' => '/_profiler', // this is default route
            ));
            //$p->boot($app);
            $app['web_profiler.toolbar'] = true;
            $app['web_profiler.debug_toolbar.position'] = 'bottom';

            /*
             * register ladybug as service
             */
            $app->register( new Rswork\Silex\Provider\LadybugServiceProvider() );
            $app->register(new Sorien\Provider\DoctrineProfilerServiceProvider());
        }

        /**
         * register combine service
         */
        $app->register(
            $combine = new Rswork\Silex\Provider\CombineServiceProvider(),
            array(
                'combine.base_path' => isset( $app['combine.base_path'] ) ? $app['combine.base_path'] : $app['public_dir'],
                'combine.cache_path' => isset( $app['combine.cache_path'] ) ? $app['combine.cache_path'] : $app['kernel.cache_dir'] . '/combine',
                'combine.mount_to' => '/',
                'combine.match_url' => '/combo',
                'combine.js_path' => '',
                'combine.css_path' => '',
            )
        );

        /**
         * app extend
         */
        $app->register(new Rswork\Silex\RouterProvider());

        // others services register in here
        // ...

        $app->error( array($this, 'onError') );
    }

    public function getName()
    {
        if( $app['debug'] ) {
            return $this->name . '(in debug mode)';
        } else {
            return $this->name;
        }
    }

    public function getRootDir()
    {
        if (null === $this->rootDir) {
            $r = new \ReflectionObject($this);
            $this->rootDir = str_replace('\\', '/', dirname(dirname($r->getFileName())));
        }

        return $this->rootDir;
    }

    public function getCacheDir()
    {
        return $this->rootDir . '/var/cache';
    }

    public function getLogDir()
    {
        return $this->rootDir . '/var/log';
    }

    public function run( Request $request = null )
    {
        $dirs = array('cache' => $this->getCacheDir(), 'log' => $this->getLogDir());

        if( isset( $this['serializer.cache.directory'] ) ) {
            $dirs['jms'] = $this['serializer.cache.directory'];
        }

        if( isset( $this['profiler.cache_dir'] ) ) {
            $dirs['profiler'] = $this['profiler.cache_dir'];
        }

        if( isset( $this['combine.cache_path'] ) ) {
            $dirs['combine'] = $this['combine.cache_path'];
        }

        foreach ($dirs as $name => $dir) {
            if (!is_dir($dir)) {
                if (false === @mkdir($dir, 0777, true)) {
                    throw new \RuntimeException(sprintf("Unable to create the %s directory (%s)\n", $name, $dir));
                }
            } elseif (!is_writable($dir)) {
                throw new \RuntimeException(sprintf("Unable to write in the %s directory (%s)\n", $name, $dir));
            }
        }

        parent::run( $request );
    }

    public function onError( \Exception $e, $code )
    {
        // 404.html, or 40x.html, or 4xx.html, or error.html
        $templates = array(
            'errors/'.$code.'.html',
            'errors/'.substr($code, 0, 2).'x.html',
            'errors/'.substr($code, 0, 1).'xx.html',
            'errors/default.html',
        );

        return new Response($this['twig']->resolveTemplate($templates)->render(array('code' => $code, 'msg' => $e->getMessage())), $code);
    }
}

