<?php

declare(strict_types=1);
/**
 * This file is part of the extension library for Hyperf.
 *
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace OnixSystemsPHP\HyperfMailer;

use Hyperf\Collection\Arr;
use Hyperf\Contract\ConfigInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Stringable\Str;
use OnixSystemsPHP\HyperfMailer\Concern\PendingMailable;
use OnixSystemsPHP\HyperfMailer\Contract\MailerInterface;
use OnixSystemsPHP\HyperfMailer\Contract\MailManagerInterface;
use OnixSystemsPHP\HyperfMailer\Transport\ArrayTransport;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\FailoverTransport;
use Symfony\Component\Mailer\Transport\RoundRobinTransport;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Hyperf\Support\make;

/**
 * @mixin Mailer
 */
class MailManager implements MailManagerInterface
{
    use PendingMailable;

    /**
     * The config instance.
     */
    protected ConfigInterface $config;

    /**
     * The array of resolved mailers.
     *
     * @var Mailer[]
     */
    protected array $mailers = [];

    /**
     * The registered custom driver creators.
     */
    protected array $customCreators = [];

    /**
     * Create a new Mail manager instance.
     */
    public function __construct(protected ContainerInterface $container)
    {
        $this->config = $container->get(ConfigInterface::class);
    }

    /**
     * Dynamically call the default driver instance.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->mailer()->{$method}(...$parameters);
    }

    /**
     * Get a mailer instance by name.
     */
    public function mailer(?string $name = null): MailerInterface
    {
        $name = $name ?: $this->getDefaultDriver();

        return $this->mailers[$name] = $this->get($name);
    }

    /**
     * Get a mailer instance by name.
     */
    public function get(string $name): MailerInterface
    {
        return $this->mailers[$name] ?? $this->resolve($name);
    }

    /**
     * Get a mailer driver instance.
     */
    public function driver(string $driver = null): Mailer|MailerInterface
    {
        return $this->mailer($driver);
    }

    /**
     * Create a new transport instance.
     */
    public function createSymfonyTransport(array $config): TransportInterface
    {
        // Here we will check if the "transport" key exists and if it doesn't we will
        // assume an application is still using the legacy mail configuration file
        // format and use the "mail.driver" configuration option instead for BC.
        $transport = $config['transport'] ?? $this->config->get('mail.default');

        if (isset($this->customCreators[$transport])) {
            return call_user_func($this->customCreators[$transport], $config);
        }

        if (trim($transport ?? '') === ''
            || ! method_exists($this, $method = 'create' . ucfirst(Str::camel($transport)) . 'Transport')) {
            throw new \InvalidArgumentException("Unsupported mail transport [{$transport}].");
        }

        return $this->{$method}($config);
    }

    /**
     * Disconnect the given mailer and remove from local cache.
     */
    public function purge(string $name = null): void
    {
        $name = $name ?: $this->getDefaultDriver();

        unset($this->mailers[$name]);
    }

    /**
     * Register a custom transport creator Closure.
     */
    public function extend(string $driver, \Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Forget all of the resolved mailer instances.
     *
     * @return $this
     */
    public function forgetMailers(): static
    {
        $this->mailers = [];

        return $this;
    }

    /**
     * Create a new transport instance.
     */
    protected function createTransport(array $config): TransportInterface
    {
        if (! empty($config['transport'])) {
            return make($config['transport'], ['options' => $config['options'] ?? []]);
        }

        if (empty($config['dsn'])) {
            throw new \InvalidArgumentException('The mail transport DSN must be specified.');
        }

        $logger = null;
        if (($loggerConfig = $this->config->get('mail.logger')) && $loggerConfig['enabled'] === true) {
            $logger = $this->container->get(LoggerFactory::class)->get(
                $loggerConfig['name'] ?? 'mail',
                $loggerConfig['group'] ?? 'default'
            );
        }

        return Transport::fromDsn($config['dsn'], null, null, $logger);
    }

    /**
     * Get the default mail driver name.
     */
    protected function getDefaultDriver(): string
    {
        return $this->config->get('mail.default');
    }

    /**
     * Resolve the given mailer.
     *
     * @throws \InvalidArgumentException
     */
    protected function resolve(string $name): Mailer
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new \InvalidArgumentException("Mailer [{$name}] is not defined.");
        }

        // Once we have created the mailer instance we will set a container instance
        // on the mailer. This allows us to resolve mailer classes via containers
        // for maximum testability on said classes instead of passing Closures.
        $mailer = make(Mailer::class, ['name' => $name, 'transport' => $this->createTransport($config)]);

        // Next we will set all of the global addresses on this mailer, which allows
        // for easy unification of all "from" addresses as well as easy debugging
        // of sent messages since these will be sent to a single email address.
        foreach (['from', 'reply_to', 'to', 'return_path'] as $type) {
            $this->setGlobalAddress($mailer, $config, $type);
        }

        return $mailer;
    }

    /**
     * Create an instance of the Symfony Failover Transport driver.
     */
    protected function createFailoverTransport(array $config): FailoverTransport
    {
        $transports = [];

        foreach ($config['mailers'] as $name) {
            $config = $this->getConfig($name);

            if (is_null($config)) {
                throw new \InvalidArgumentException("Mailer [{$name}] is not defined.");
            }

            // Now, we will check if the "driver" key exists and if it does we will set
            // the transport configuration parameter in order to offer compatibility
            // with any Laravel <= 6.x application style mail configuration files.
            $transports[] = $this->config->get('mail.default')
                ? $this->createSymfonyTransport(array_merge($config, ['transport' => $name]))
                : $this->createSymfonyTransport($config);
        }

        return new FailoverTransport($transports);
    }

    /**
     * Create an instance of the Symfony Roundrobin Transport driver.
     */
    protected function createRoundrobinTransport(array $config): RoundRobinTransport
    {
        $transports = [];

        foreach ($config['mailers'] as $name) {
            $config = $this->getConfig($name);

            if (is_null($config)) {
                throw new \InvalidArgumentException("Mailer [{$name}] is not defined.");
            }

            // Now, we will check if the "driver" key exists and if it does we will set
            // the transport configuration parameter in order to offer compatibility
            // with any Laravel <= 6.x application style mail configuration files.
            $transports[] = $this->config->get('mail.default')
                ? $this->createSymfonyTransport(array_merge($config, ['transport' => $name]))
                : $this->createSymfonyTransport($config);
        }

        return new RoundRobinTransport($transports);
    }

    /**
     * Create an instance of the Array Transport Driver.
     */
    protected function createArrayTransport(): ArrayTransport
    {
        return new ArrayTransport();
    }

    /**
     * Create the Symfony Mailer instance for the given configuration.
     */
    protected function createSymfonyMailer(array $config): SymfonyMailer
    {
        return new SymfonyMailer($this->createTransport($config));
    }

    /**
     * Get a configured Symfony HTTP client instance.
     */
    protected function getHttpClient(array $config): null|HttpClientInterface
    {
        if ($options = ($config['client'] ?? false)) {
            $maxHostConnections = Arr::pull($options, 'max_host_connections', 6);
            $maxPendingPushes = Arr::pull($options, 'max_pending_pushes', 50);

            return HttpClient::create($options, $maxHostConnections, $maxPendingPushes);
        }
        return null;
    }

    /**
     * Set a global address on the mailer by type.
     */
    protected function setGlobalAddress(MailerInterface $mailer, array $config, string $type): void
    {
        $address = Arr::get($config, $type, $this->config->get('mail.' . $type));

        if (is_array($address) && isset($address['address'])) {
            $mailer->{'always' . Str::studly($type)}($address['address'], $address['name']);
        }
    }

    /**
     * Get the mail connection configuration.
     */
    protected function getConfig(string $name): array
    {
        return $this->config->get("mail.mailers.{$name}");
    }
}
