<?php

namespace Ftrrtf\RollbarBundle\Tests\DependencyInjection;

use Ftrrtf\RollbarBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidConfiguration()
    {
        $processor = new Processor();
        $processor->processConfiguration(new Configuration(), array());
    }

    /**
     * @dataProvider configurationDataProvider
     */
    public function testValidConfiguration($options, $configKey, $expectedConfig)
    {
        $processor = new Processor();
        $actualConfig = $processor->processConfiguration(
            new Configuration(),
            array($options)
        );
        static::assertEquals($expectedConfig, $actualConfig[$configKey]);
    }

    public function configurationDataProvider()
    {
        return array(
            'server curl transport configuration' => array(
                'options' => array(
                    'notifier' => array(
                        'server' => array(
                            'transport' => array(
                                'type' => 'curl',
                                'access_token' => 'token',
                            ),
                        ),
                    ),
                ),
                'check config key' => 'notifier',
                'expected config' => array(
                    'server' => array(
                        'batched' => false,
                        'batch_size' => '50',
                        'transport' => array(
                            'type' => 'curl',
                            'access_token' => 'token',
                        ),
                    ),
                ),
            ),
            'server agent transport configuration' => array(
                'options' => array(
                    'notifier' => array(
                        'server' => array(
                            'transport' => array(
                                'type' => 'agent',
                                'agent_log_location' => '/path/to/log',
                            ),
                        ),
                    ),
                ),
                'check config key' => 'notifier',
                'expected config' => array(
                    'server' => array(
                        'batched' => false,
                        'batch_size' => '50',
                        'transport' => array(
                            'type' => 'agent',
                            'agent_log_location' => '/path/to/log',
                        ),
                    ),
                ),
            ),
            'client js configuration' => array(
                'options' => array(
                    'notifier' => array(
                        'client' => array(
                            'access_token' => 'token',
                        ),
                    ),
                ),
                'check config key' => 'notifier',
                'expected config' => array(
                    'client' => array(
                        'access_token' => 'token',
                        'source_map_enabled' => false,
                        'code_version' => '',
                        'guess_uncaught_frames' => false,
                        'rollbarjs_version' => 'v1',
                        'allowed_js_hosts' => array(),
                        'check_ignore_function_provider' => 'ftrrtf_rollbar.check_ignore_function_provider.default',
                        'transform_payload_function_provider' => 'ftrrtf_rollbar.transform_payload_function_provider.default',
                    ),
                ),
            ),
            'environment configuration' => array(
                'options' => array(
                    'notifier' => array(),
                    'environment' => array(
                        'environment' => 'production',
                        'branch' => 'master',
                        'code_version' => '12345',
                    ),
                ),
                'check config key' => 'environment',
                'expected config' => array(
                    'environment' => 'production',
                    'branch' => 'master',
                    'root_dir' => '',
                    'code_version' => '12345',
                    'anonymize' => false,
                ),
            ),
            'anonymized environment configuration' => array(
                'options' => array(
                    'notifier' => array(),
                    'environment' => array(
                        'environment' => 'production',
                        'branch' => 'master',
                        'code_version' => '12345',
                        'anonymize' => true,
                    ),
                ),
                'check config key' => 'environment',
                'expected config' => array(
                    'environment' => 'production',
                    'branch' => 'master',
                    'root_dir' => '',
                    'code_version' => '12345',
                    'anonymize' => true,
                ),
            ),
        );
    }
}
