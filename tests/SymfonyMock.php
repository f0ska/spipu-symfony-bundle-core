<?php

/**
 * This file is part of a Spipu Bundle
 *
 * (c) Laurent Minguet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spipu\CoreBundle\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionClass;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\Type\FormTypeHttpFoundationExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Twig\Environment as Twig_Environment;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasher;

class SymfonyMock extends TestCase
{
    /**
     * @var string[]
     */
    protected static array $consoleOutputMessages = [];

    /**
     * @var array
     */
    protected static array $containerBuilderParameters = [];

    /**
     * @param TestCase $testCase
     * @param array $services
     * @param array $parameters
     * @return MockObject|ContainerInterface
     */
    public static function getContainer(TestCase $testCase, array $services = [], array $parameters = []): ContainerInterface
    {
        $container = $testCase->createMock(ContainerInterface::class);

        $map = [];
        foreach ($services as $key => $value) {
            $map[] = [$key, 1, $value];
        }
        $container->method('get')->willReturnMap($map);

        $map = [];
        foreach (array_keys($services) as $key) {
            $map[] = [$key, true];
        }
        $container->method('has')->willReturnMap($map);

        $map = [];
        foreach ($parameters as $key => $value) {
            $map[] = [$key, $value];
        }
        $container->method('getParameter')->willReturnMap($map);

        $map = [];
        foreach (array_keys($parameters) as $key) {
            $map[] = [$key, true];
        }
        $container->method('hasParameter')->willReturnMap($map);

        /** @var MockObject|ContainerInterface $container */
        return $container;
    }

    /**
     * @param TestCase $testCase
     * @param array $extensions
     * @return MockObject|ContainerBuilder
     */
    public static function getContainerBuilder(TestCase $testCase, array $extensions = []): ContainerBuilder
    {
        self::$containerBuilderParameters = [];

        $containerBuilder = $testCase->createMock(ContainerBuilder::class);

        $containerBuilder
            ->method('getParameterBag')
            ->willReturn(self::getParameterBag($testCase));

        $containerBuilder
            ->method('setParameter')
            ->willReturnCallback(
                function (string $name, mixed $value) {
                    SymfonyMock::$containerBuilderParameters[$name] = $value;
                }
            );

        $containerBuilder
            ->method('hasParameter')
            ->willReturnCallback(
                function (string $name) {
                    return array_key_exists($name, SymfonyMock::$containerBuilderParameters);
                }
            );

        $containerBuilder
            ->method('getParameter')
            ->willReturnCallback(
                function (string $name) {
                    return SymfonyMock::$containerBuilderParameters[$name];
                }
            );

        $containerBuilder
            ->method('prependExtensionConfig')
            ->willReturnCallback(
                function (string $name, array $config) {
                    $name = 'prependConfig::' . $name;
                    SymfonyMock::$containerBuilderParameters[$name][] = $config;
                }
            );

        $containerBuilder
            ->method('getExtensionConfig')
            ->willReturnCallback(
                function (string $name) {
                    $name = 'prependConfig::' . $name;
                    return SymfonyMock::$containerBuilderParameters[$name];
                }
            );

        $containerBuilder->method('getReflectionClass')->willReturnCallback(
            function (string $class, bool $throw = true) {
                return new ReflectionClass($class);
            }
        );

        $containerBuilder->method('setAlias')->willReturnCallback(
            function (string $alias, string $id) {
                return new Alias($id);
            }
        );

        $containerBuilder->method('getExtensions')->willReturn($extensions);

        /** @var MockObject|ContainerBuilder $containerBuilder */
        return $containerBuilder;
    }

    /**
     * @param TestCase $testCase
     * @return ContainerConfigurator
     */
    public static function getContainerConfigurator(TestCase $testCase): ContainerConfigurator
    {
        $phpFileLoader = $testCase->createMock(PhpFileLoader::class);
        $instanceOf = [];

        return new ContainerConfigurator(
            self::getContainerBuilder($testCase),
            $phpFileLoader,
            $instanceOf,
            '',
            '',
            null
        );
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|ParameterBagInterface
     */
    public static function getParameterBag(TestCase $testCase): ParameterBagInterface
    {
        $parameterBag = $testCase->createMock(ParameterBagInterface::class);

        $parameterBag->method('resolveValue')->willReturnArgument(0);
        $parameterBag->method('unescapeValue')->willReturnArgument(0);

        /** @var MockObject|ParameterBagInterface $parameterBag */
        return $parameterBag;
    }

    /**
     * @param TestCase $testCase
     * @param array $getValues
     * @return MockObject|RequestStack
     */
    public static function getRequestStack(TestCase $testCase, array $getValues = []): RequestStack
    {
        $session = self::getSession($testCase);

        $request = new Request();
        $request->initialize($getValues);
        $request->setSession($session);
        $request->attributes->set('_route', 'fake_route');

        $requestStack = $testCase->createMock(RequestStack::class);
        $requestStack->expects($testCase->any())->method('getCurrentRequest')->willReturn($request);
        $requestStack->expects($testCase->any())->method('getSession')->willReturn($session);

        /** @var MockObject|RequestStack $requestStack */
        return $requestStack;
    }

    /**
     * @param TestCase $testCase
     * @return Session
     */
    public static function getSession(TestCase $testCase): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|RouterInterface
     */
    public static function getRouter(TestCase $testCase): RouterInterface
    {
        $router = $testCase->createMock(RouterInterface::class);
        $router
            ->expects($testCase->any())
            ->method('generate')
            ->willReturnCallback(
                function (string $name, array $parameters = []) {
                    $url = '/' . $name . '/';
                    if ($parameters && $query = http_build_query($parameters, '', '&', PHP_QUERY_RFC3986)) {
                        $url .= '?' . strtr($query, ['%2F' => '/']);
                    }
                    return $url;
                }
            );

        /** @var MockObject|RouterInterface $router */
        return $router;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|TranslatorInterface
     */
    public static function getTranslator(TestCase $testCase): TranslatorInterface
    {
        $translator = $testCase->createMock(TranslatorInterface::class);
        $translator
            ->expects($testCase->any())
            ->method('trans')
            ->willReturnArgument(0);

        /** @var MockObject|TranslatorInterface $translator */
        return $translator;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|EventDispatcherInterface
     */
    public static function getEventDispatcher(TestCase $testCase): EventDispatcherInterface
    {
        return $testCase->createMock(EventDispatcherInterface::class);
    }

    /**
     * @param TestCase $testCase
     * @param array $grantedRoles
     * @return MockObject|AuthorizationCheckerInterface
     */
    public static function getAuthorizationChecker(TestCase $testCase, array $grantedRoles = []): AuthorizationCheckerInterface
    {
        if ($grantedRoles === []) {
            $grantedRoles = [
                'IS_AUTHENTICATED_REMEMBERED',
                'ROLE_GOOD',
            ];
        }
        $authorizationChecker = $testCase->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker
            ->expects($testCase->any())
            ->method('isGranted')
            ->willReturnCallback(
                function (mixed $attribute, mixed $subject = null) use ($grantedRoles) {
                    return (is_string($attribute) && in_array($attribute, $grantedRoles));
                }
            );

        /** @var MockObject|AuthorizationCheckerInterface $authorizationChecker */
        return $authorizationChecker;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|ClassMetadata
     */
    public static function getEntityMetaData(TestCase $testCase): ClassMetadata
    {
        return $testCase->createMock(ClassMetadata::class);
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|EntityManagerInterface
     */
    public static function getEntityManager(TestCase $testCase): EntityManagerInterface
    {
        $entityManager = $testCase->createMock(EntityManagerInterface::class);

        $entityManager
            ->method('getClassMetadata')
            ->willReturn(self::getEntityMetaData($testCase));

        $queryBuilder = self::getDoctrineQueryBuilder($testCase);
        $queryBuilder
            ->method('getEntityManager')
            ->willReturn($entityManager);

        $entityManager
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $connection = self::getConnection($testCase);
        $entityManager
            ->method('getConnection')
            ->willReturn($connection);

        /** @var MockObject|EntityManagerInterface $entityManager */
        return $entityManager;
    }

    /**
     * @param TestCase $testCase
     * @return Connection|MockObject
     */
    public static function getConnection(TestCase $testCase): Connection
    {
        $connection = $testCase->createMock(Connection::class);

        $connection
            ->method('quote')
            ->willReturnCallback(
                function (string $value) {
                    return "'" . addslashes($value) . "'";
                }
            );

        /** @var MockObject|Connection $registry */
        return $connection;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|ManagerRegistry
     */
    public static function getEntityRegistry(TestCase $testCase): ManagerRegistry
    {
        $registry = $testCase->createMock(ManagerRegistry::class);

        $registry
            ->method('getManagerForClass')
            ->willReturn(SymfonyMock::getEntityManager($testCase));

        /** @var MockObject|ManagerRegistry $registry */
        return $registry;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|Twig_Environment
     */
    public static function getTwig(TestCase $testCase): Twig_Environment
    {
        return $testCase->createMock(Twig_Environment::class);
    }

    /**
     * @param TestCase $testCase
     * @return CacheItemPoolInterface
     */
    public static function getCachePool(TestCase $testCase): CacheItemPoolInterface
    {
        return new ArrayAdapter(0, false);
    }

    /**
     * @param TestCase $testCase
     * @param MockObject $file
     * @param string $fileName
     * @param string $guessExtension
     * @param string $mimeType
     * @return MockObject
     */
    protected static function prepareFile(
        TestCase $testCase,
        MockObject $file,
        string $fileName = '/tmp/image.jpg',
        string $guessExtension = 'jpeg',
        string $mimeType = 'image/jpeg'
    ): MockObject {
        $tempFile = new File($fileName, false);

        $file->method('getPath')->willReturn($tempFile->getPath());
        $file->method('getFilename')->willReturn($tempFile->getFilename());
        $file->method('getExtension')->willReturn($tempFile->getExtension());
        $file->method('getBasename')->willReturn($tempFile->getBasename());
        $file->method('getPathname')->willReturn($tempFile->getPathname());
        $file->method('getBasename')->willReturn($tempFile->getBasename());
        $file->method('getRealPath')->willReturn($tempFile->getRealPath());
        $file->method('getPathInfo')->willReturn($tempFile->getPathInfo());
        $file->method('getType')->willReturn('file');
        $file->method('getSize')->willReturn(42);
        $file->method('isDir')->willReturn(false);
        $file->method('isFile')->willReturn(true);
        $file->method('isLink')->willReturn(false);
        $file->method('isExecutable')->willReturn(false);
        $file->method('isReadable')->willReturn(true);
        $file->method('isWritable')->willReturn(true);

        $file->method('guessExtension')->willReturn($guessExtension);
        $file->method('getMimeType')->willReturn($mimeType);

        $file->method('move')->willReturnCallback(
            function (string $directory, ?string $name = null) use ($file, $testCase, $fileName, $guessExtension, $mimeType): File {
                $newName = $fileName;
                if ($name !== null) {
                    $newName = $name;
                }
                $target = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $newName;
                return SymfonyMock::getFile($testCase, $target, $guessExtension, $mimeType);
            }
        );

        return $file;
    }

    /**
     * @param TestCase $testCase
     * @param string $fileName
     * @param string $guessExtension
     * @param string $mimeType
     * @return MockObject|File
     */
    public static function getFile(
        TestCase $testCase,
        string $fileName = '/tmp/image.jpg',
        string $guessExtension = 'jpeg',
        string $mimeType = 'image/jpeg'
    ): File {
        $file = $testCase->createMock(File::class);

        self::prepareFile($testCase, $file, $fileName, $guessExtension, $mimeType);

        /** @var MockObject|File $file */
        return $file;
    }

    /**
     * @param TestCase $testCase
     * @param string $path
     * @param string $originalFileName
     * @param string $guessExtension
     * @param string $mimeType
     * @return MockObject|UploadedFile
     */
    public static function getUploadedFile(
        TestCase $testCase,
        string $path = '/tmp/uploaded_image.jpg',
        string $originalFileName = 'image.jpg',
        string $guessExtension = 'jpeg',
        string $mimeType = 'image/jpeg'
    ): UploadedFile {
        $file = $testCase->createMock(UploadedFile::class);

        self::prepareFile($testCase, $file, $path, $guessExtension, $mimeType);

        $file->method('getClientOriginalName')->willReturn($originalFileName);
        $file->method('getClientOriginalExtension')->willReturn(pathinfo($originalFileName, PATHINFO_EXTENSION));
        $file->method('getClientMimeType')->willReturn($mimeType);
        $file->method('guessClientExtension')->willReturn(null);
        $file->method('isValid')->willReturn(true);

        /** @var MockObject|UploadedFile $file */
        return $file;
    }

    /**
     * @param TestCase $testCase
     * @param array $arguments
     * @param array $options
     * @return MockObject|InputInterface
     */
    public static function getConsoleInput(TestCase $testCase, array $arguments = [], array $options = []): InputInterface
    {
        $input = $testCase->createMock(InputInterface::class);

        $map = [];
        foreach ($arguments as $key => $value) {
            $map[] = [$key, $value];
        }
        $input->method('getArgument')->willReturnMap($map);

        $map = [];
        foreach ($options as $key => $value) {
            $map[] = [$key, $value];
        }
        $input->method('getOption')->willReturnMap($map);

        /** @var MockObject|InputInterface $input */
        return $input;
    }

    /**
     * @param TestCase $testCase
     * @return Stub|OutputInterface
     */
    public static function getConsoleOutput(TestCase $testCase): OutputInterface
    {
        self::$consoleOutputMessages = [];

        $output = $testCase->createStub(OutputInterface::class);
        $formatter = $testCase->createStub(OutputFormatterInterface::class);

        $formatter
            ->method('isDecorated')
            ->willReturn(false);
        $output
            ->method('getFormatter')
            ->willReturn($formatter);

        $output
            ->method('write')
            ->willReturnCallback(
                function (mixed $messages, bool $newline = false, int $options = 0) {
                    if (!is_array($messages)) {
                        $messages = [(string) $messages];
                    }
                    foreach ($messages as $key => $value) {
                        $messages[$key] = trim(strip_tags($value));
                    }

                    self::$consoleOutputMessages = array_merge(self::$consoleOutputMessages, array_values($messages));
                }
            );

        $output
            ->method('writeln')
            ->willReturnCallback(
                function (mixed $messages, int $options = 0) use ($output) {
                    $output->write($messages, true, $options);
                }
            );

        /** @var Stub|OutputInterface $output */
        return $output;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|SymfonyStyle
     */
    public static function getConsoleSymfonyStyle(TestCase $testCase): SymfonyStyle
    {
        self::$consoleOutputMessages = [];

        $symfonyStyle = $testCase->createMock(SymfonyStyle::class);

        $symfonyStyle
            ->method('writeln')
            ->willReturnCallback(
                function (mixed $messages, int $type = 1) {
                    if (!is_array($messages)) {
                        $messages = [(string) $messages];
                    }
                    foreach ($messages as $key => $value) {
                        $messages[$key] = trim(strip_tags($value));
                    }

                    self::$consoleOutputMessages = array_merge(self::$consoleOutputMessages, array_values($messages));
                }
            );

        $symfonyStyle
            ->method('text')
            ->willReturnCallback(
                function (mixed $messages) use ($symfonyStyle) {
                    $symfonyStyle->writeln($messages);
                }
            );

        /** @var MockObject|SymfonyStyle $symfonyStyle */
        return $symfonyStyle;
    }

    /**
     * @return string[]
     */
    public static function getConsoleOutputResult(): array
    {
        return self::$consoleOutputMessages;
    }

    /**
     * @param TestCase $testCase
     * @param string $tokenValue
     * @return FormFactoryInterface
     */
    public static function getFormFactory(
        TestCase $testCase,
        string $tokenValue = 'mock_token_value'
    ): FormFactoryInterface {
        $tokenManager = $testCase->createMock(CsrfTokenManagerInterface::class);
        $tokenManager->method('getToken')->willReturnCallback(
            function (string $tokenId) use ($tokenValue) {
                return new CsrfToken($tokenId, $tokenValue);
            }
        );
        $tokenManager->method('isTokenValid')->willReturn(true);

        /** @var CsrfTokenManagerInterface $tokenManager */
        $extensions = [
            new CsrfExtension($tokenManager),
        ];

        $typeExtensions = [
            new FormTypeHttpFoundationExtension(),
        ];

        return Forms::createFormFactoryBuilder()
            ->addExtensions($extensions)
            ->addTypeExtensions($typeExtensions)
            ->addTypes([])
            ->addTypeGuessers([])
            ->getFormFactory();
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|TokenStorageInterface
     */
    public static function getTokenStorage(TestCase $testCase): TokenStorageInterface
    {
        $token = $testCase->createMock(TokenInterface::class);

        $tokenStorage = $testCase->createMock(TokenStorageInterface::class);

        $tokenStorage
            ->method('getToken')
            ->willReturn($token);

        /** @var MockObject|TokenStorageInterface $tokenStorage */
        return $tokenStorage;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|PasswordHasherInterface
     */
    public static function getPasswordHasher(TestCase $testCase): PasswordHasherInterface
    {
        $hasher = $testCase->createMock(PasswordHasherInterface::class);

        $hasher
            ->method('hash')
            ->willReturnCallback(
                function (string $plainPassword) {
                    return 'encoded_' . $plainPassword;
                }
            );

        $hasher
            ->method('verify')
            ->willReturnCallback(
                function (string $hashedPassword, string $plainPassword) use ($hasher) {
                    return $hashedPassword === $hasher->hash($plainPassword);
                }
            );

        /** @var MockObject|PasswordHasherInterface $hasher */
        return $hasher;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|PasswordHasherFactoryInterface
     */
    public static function getPasswordHasherFactory(TestCase $testCase): PasswordHasherFactoryInterface
    {
        $hasherFactory = $testCase->createMock(PasswordHasherFactoryInterface::class);
        $hasherFactory
            ->method('getPasswordHasher')
            ->willReturn(self::getPasswordHasher($testCase));

        /** @var MockObject|PasswordHasherFactoryInterface $hasherFactory */
        return $hasherFactory;
    }

    /**
     * @param TestCase $testCase
     * @param UserInterface $user
     * @return MockObject|UserProviderInterface
     */
    public static function getUserProvider(TestCase $testCase, UserInterface $user): UserProviderInterface
    {
        $userProvider = $testCase->createMock(UserProviderInterface::class);
        $userProvider
            ->expects($testCase->once())
            ->method('loadUserByIdentifier')
            ->with($user->getUserIdentifier())
            ->willReturn($user);

        /** @var MockObject|UserProviderInterface $userProvider */
        return $userProvider;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|UserPasswordHasher
     */
    public static function getUserPasswordHasher(TestCase $testCase): UserPasswordHasher
    {
        $hasher = $testCase->createMock(UserPasswordHasher::class);

        $hasher
            ->method('hashPassword')
            ->willReturnCallback(
                function (PasswordAuthenticatedUserInterface $user, string $plainPassword) {
                    return 'encoded_' . $plainPassword;
                }
            );

        $hasher
            ->method('isPasswordValid')
            ->willReturnCallback(
                function (PasswordAuthenticatedUserInterface $user, string $raw) use ($hasher) {
                    return $user->getPassword() === $hasher->hashPassword($user, $raw);
                }
            );

        return $hasher;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|QueryBuilder
     */
    public static function getDoctrineQueryBuilder(TestCase $testCase): QueryBuilder
    {
        $builder = $testCase->createMock(QueryBuilder::class);

        $methods = [
            'select',
            'add',
            'addSelect',
            'update',
            'delete',
            'from',
            'where',
            'andWhere',
            'orWhere',
            'groupBy',
            'addGroupBy',
            'join',
            'innerJoin',
            'leftJoin',
            'set',
            'indexBy',
            'distinct',
            'setParameter',
            'setParameters',
            'setFirstResult',
            'setMaxResults',
            'setCacheable',
            'setCacheRegion',
            'setLifetime',
            'setCacheMode',
        ];

        foreach ($methods as $method) {
            $builder
                ->method($method)
                ->willReturn($builder);
        }

        $builder
            ->method('getQuery')
            ->willReturn(self::getDoctrineQuery($testCase));

        /** @var MockObject|QueryBuilder $builder */
        return $builder;
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|Query
     */
    public static function getDoctrineQuery(TestCase $testCase): Query
    {
        return $testCase->createMock(Query::class);
    }

    /**
     * @param TestCase $testCase
     * @return MockObject|Security
     */
    public static function getSecurity(TestCase $testCase): Security
    {
        $user = new InMemoryUser('42', 'pass');

        $security = $testCase->createMock(Security::class);

        $security
            ->expects($testCase->any())
            ->method('getUser')
            ->willReturn($user);

        return $security;
    }
}
