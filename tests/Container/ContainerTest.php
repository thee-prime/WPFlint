<?php

declare(strict_types=1);

namespace WPFlint\Tests\Container;

use Closure;
use WP_Mock;
use WP_Mock\Tools\TestCase;
use WPFlint\Container\Container;
use WPFlint\Container\ContainerException;
use WPFlint\Container\NotFoundException;

/**
 * @covers \WPFlint\Container\Container
 * @covers \WPFlint\Container\ContextualBindingBuilder
 */
class ContainerTest extends TestCase
{
    /**
     * @var Container
     */
    protected Container $container;

    public function setUp(): void
    {
        parent::setUp();
        WP_Mock::setUp();

        $this->container = new Container();
    }

    public function tearDown(): void
    {
        WP_Mock::tearDown();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // bind() + make()
    // ---------------------------------------------------------------

    public function testBindConcreteClass(): void
    {
        $this->container->bind(StubInterface::class, StubConcrete::class);

        $result = $this->container->make(StubInterface::class);

        $this->assertInstanceOf(StubConcrete::class, $result);
    }

    public function testBindClosure(): void
    {
        $this->container->bind('greeting', function () {
            return 'hello';
        });

        $this->assertSame('hello', $this->container->make('greeting'));
    }

    public function testBindReturnsNewInstanceEachTime(): void
    {
        $this->container->bind(StubConcrete::class);

        $a = $this->container->make(StubConcrete::class);
        $b = $this->container->make(StubConcrete::class);

        $this->assertNotSame($a, $b);
    }

    public function testBindOverwritesPreviousBinding(): void
    {
        $this->container->bind('val', function () {
            return 'first';
        });
        $this->container->bind('val', function () {
            return 'second';
        });

        $this->assertSame('second', $this->container->make('val'));
    }

    // ---------------------------------------------------------------
    // singleton()
    // ---------------------------------------------------------------

    public function testSingletonReturnsSameInstance(): void
    {
        $this->container->singleton(StubInterface::class, StubConcrete::class);

        $a = $this->container->make(StubInterface::class);
        $b = $this->container->make(StubInterface::class);

        $this->assertSame($a, $b);
    }

    public function testSingletonWithClosure(): void
    {
        $this->container->singleton('counter', function () {
            return new StubConcrete();
        });

        $a = $this->container->make('counter');
        $b = $this->container->make('counter');

        $this->assertSame($a, $b);
    }

    // ---------------------------------------------------------------
    // instance()
    // ---------------------------------------------------------------

    public function testInstanceRegistersExistingObject(): void
    {
        $obj = new StubConcrete();

        $this->container->instance(StubInterface::class, $obj);

        $this->assertSame($obj, $this->container->make(StubInterface::class));
    }

    public function testInstanceReturnsTheRegisteredValue(): void
    {
        $obj    = new StubConcrete();
        $result = $this->container->instance('test', $obj);

        $this->assertSame($obj, $result);
    }

    // ---------------------------------------------------------------
    // has()
    // ---------------------------------------------------------------

    public function testHasReturnsTrueForBinding(): void
    {
        $this->container->bind('foo', function () {
            return 'bar';
        });

        $this->assertTrue($this->container->has('foo'));
    }

    public function testHasReturnsTrueForInstance(): void
    {
        $this->container->instance('foo', 'bar');

        $this->assertTrue($this->container->has('foo'));
    }

    public function testHasReturnsFalseForUnregistered(): void
    {
        $this->assertFalse($this->container->has('nope'));
    }

    // ---------------------------------------------------------------
    // get() — PSR-11
    // ---------------------------------------------------------------

    public function testGetIsAliasForMake(): void
    {
        $this->container->instance('key', 'value');

        $this->assertSame('value', $this->container->get('key'));
    }

    // ---------------------------------------------------------------
    // forget()
    // ---------------------------------------------------------------

    public function testForgetRemovesBindingAndInstance(): void
    {
        $this->container->singleton('svc', function () {
            return 'resolved';
        });

        // Resolve to cache the singleton.
        $this->container->make('svc');

        $this->container->forget('svc');

        $this->assertFalse($this->container->has('svc'));
    }

    // ---------------------------------------------------------------
    // Auto-resolution
    // ---------------------------------------------------------------

    public function testAutoResolvesConcreteClassWithoutBinding(): void
    {
        $result = $this->container->make(StubConcrete::class);

        $this->assertInstanceOf(StubConcrete::class, $result);
    }

    public function testAutoResolvesNestedDependencies(): void
    {
        $this->container->bind(StubInterface::class, StubConcrete::class);

        $result = $this->container->make(StubWithDependency::class);

        $this->assertInstanceOf(StubWithDependency::class, $result);
        $this->assertInstanceOf(StubConcrete::class, $result->dep);
    }

    public function testAutoResolvesDefaultParameterValues(): void
    {
        $result = $this->container->make(StubWithDefault::class);

        $this->assertInstanceOf(StubWithDefault::class, $result);
        $this->assertSame(42, $result->value);
    }

    // ---------------------------------------------------------------
    // Contextual bindings: when()->needs()->give()
    // ---------------------------------------------------------------

    public function testContextualBindingWithClass(): void
    {
        $this->container->bind(StubInterface::class, StubConcrete::class);

        $this->container
            ->when(StubConsumerA::class)
            ->needs(StubInterface::class)
            ->give(StubAlternateConcrete::class);

        $consumerA = $this->container->make(StubConsumerA::class);
        $consumerB = $this->container->make(StubConsumerB::class);

        $this->assertInstanceOf(StubAlternateConcrete::class, $consumerA->dep);
        $this->assertInstanceOf(StubConcrete::class, $consumerB->dep);
    }

    public function testContextualBindingWithClosure(): void
    {
        $this->container
            ->when(StubConsumerA::class)
            ->needs(StubInterface::class)
            ->give(function () {
                return new StubAlternateConcrete();
            });

        $this->container->bind(StubInterface::class, StubConcrete::class);

        $consumer = $this->container->make(StubConsumerA::class);

        $this->assertInstanceOf(StubAlternateConcrete::class, $consumer->dep);
    }

    // ---------------------------------------------------------------
    // Circular dependency detection
    // ---------------------------------------------------------------

    public function testCircularDependencyThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $this->container->make(StubCircularA::class);
    }

    // ---------------------------------------------------------------
    // Error cases
    // ---------------------------------------------------------------

    public function testMakeNonExistentClassThrowsNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('does not exist');

        $this->container->make('NonExistent\\FakeClass');
    }

    public function testMakeNonInstantiableClassThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('not instantiable');

        $this->container->make(StubInterface::class);
    }

    public function testUnresolvablePrimitiveThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Unresolvable dependency');

        $this->container->make(StubWithPrimitive::class);
    }

    // ---------------------------------------------------------------
    // bind() clears cached singleton
    // ---------------------------------------------------------------

    public function testRebindClearsCachedSingleton(): void
    {
        $this->container->singleton('svc', function () {
            return 'first';
        });

        $this->container->make('svc');

        $this->container->singleton('svc', function () {
            return 'second';
        });

        $this->assertSame('second', $this->container->make('svc'));
    }
}

// ---------------------------------------------------------------------------
// Test stubs — minimal classes used only by these tests.
// ---------------------------------------------------------------------------

interface StubInterface
{
}

class StubConcrete implements StubInterface
{
}

class StubAlternateConcrete implements StubInterface
{
}

class StubWithDependency
{
    public StubInterface $dep;

    public function __construct(StubInterface $dep)
    {
        $this->dep = $dep;
    }
}

class StubWithDefault
{
    public int $value;

    public function __construct(int $value = 42)
    {
        $this->value = $value;
    }
}

class StubConsumerA
{
    public StubInterface $dep;

    public function __construct(StubInterface $dep)
    {
        $this->dep = $dep;
    }
}

class StubConsumerB
{
    public StubInterface $dep;

    public function __construct(StubInterface $dep)
    {
        $this->dep = $dep;
    }
}

class StubCircularA
{
    public function __construct(StubCircularB $b)
    {
    }
}

class StubCircularB
{
    public function __construct(StubCircularA $a)
    {
    }
}

class StubWithPrimitive
{
    public function __construct(string $name)
    {
    }
}
