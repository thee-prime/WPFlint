<?php
/**
 * IoC container with auto-resolution, singletons, and contextual bindings.
 *
 * @package WPFlint\Container
 */

declare(strict_types=1);

namespace WPFlint\Container;

use Closure;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * IoC container with auto-resolution, singletons, and contextual bindings.
 */
class Container implements ContainerInterface {

	/**
	 * Registered bindings (abstract => ['concrete' => mixed, 'shared' => bool]).
	 *
	 * @var array<string, array{concrete: mixed, shared: bool}>
	 */
	protected array $bindings = array();

	/**
	 * Resolved singleton instances.
	 *
	 * @var array<string, mixed>
	 */
	protected array $instances = array();

	/**
	 * Contextual bindings: [concrete][abstract] => implementation.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected array $contextual = array();

	/**
	 * Stack of abstracts currently being resolved (circular dependency detection).
	 *
	 * @var array<string, bool>
	 */
	protected array $resolving = array();

	/**
	 * Register a binding in the container.
	 *
	 * @param string              $abstract The abstract type or alias.
	 * @param Closure|string|null $concrete The concrete implementation.
	 * @param bool                $shared   Whether the binding is a singleton.
	 *
	 * @return void
	 */
	public function bind( string $abstract, $concrete = null, bool $shared = false ): void {
		$this->bindings[ $abstract ] = array(
			'concrete' => $concrete ?? $abstract,
			'shared'   => $shared,
		);

		unset( $this->instances[ $abstract ] );
	}

	/**
	 * Register a shared (singleton) binding.
	 *
	 * @param string              $abstract The abstract type or alias.
	 * @param Closure|string|null $concrete The concrete implementation.
	 *
	 * @return void
	 */
	public function singleton( string $abstract, $concrete = null ): void {
		$this->bind( $abstract, $concrete, true );
	}

	/**
	 * Register an existing instance in the container.
	 *
	 * @param string $abstract The abstract type or alias.
	 * @param mixed  $instance The resolved instance.
	 *
	 * @return mixed The instance that was registered.
	 */
	public function instance( string $abstract, $instance ) {
		$this->instances[ $abstract ] = $instance;

		return $instance;
	}

	/**
	 * Resolve a type from the container.
	 *
	 * @param string $abstract The abstract type or alias.
	 *
	 * @return mixed
	 *
	 * @throws NotFoundException  If the abstract cannot be resolved.
	 * @throws ContainerException On circular dependency or reflection failure.
	 */
	public function make( string $abstract ) {
		return $this->resolve( $abstract );
	}

	/**
	 * PSR-11 get — alias for make().
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return mixed
	 */
	public function get( string $id ) {
		return $this->make( $id );
	}

	/**
	 * Check if the container can resolve the given abstract.
	 *
	 * @param string $id Identifier of the entry to look for.
	 *
	 * @return bool
	 */
	public function has( string $id ): bool {
		return isset( $this->bindings[ $id ] ) || isset( $this->instances[ $id ] );
	}

	/**
	 * Remove a binding and its cached instance.
	 *
	 * @param string $abstract The abstract type or alias.
	 *
	 * @return void
	 */
	public function forget( string $abstract ): void {
		unset( $this->bindings[ $abstract ], $this->instances[ $abstract ] );
	}

	/**
	 * Begin a contextual binding definition.
	 *
	 * @param string $concrete The class that needs the contextual binding.
	 *
	 * @return ContextualBindingBuilder
	 */
	public function when( string $concrete ): ContextualBindingBuilder {
		return new ContextualBindingBuilder( $this, $concrete );
	}

	/**
	 * Store a contextual binding. Called by ContextualBindingBuilder::give().
	 *
	 * @param string $concrete       The consumer class.
	 * @param string $abstract       The dependency interface/class.
	 * @param mixed  $implementation The concrete to provide.
	 *
	 * @return void
	 */
	public function add_contextual_binding( string $concrete, string $abstract, $implementation ): void {
		$this->contextual[ $concrete ][ $abstract ] = $implementation;
	}

	/**
	 * Resolve the given abstract type.
	 *
	 * @param string      $abstract The abstract type to resolve.
	 * @param string|null $context  The class requesting this dependency (for contextual bindings).
	 *
	 * @return mixed
	 *
	 * @throws NotFoundException  If the type cannot be resolved.
	 * @throws ContainerException On circular dependency or unresolvable parameter.
	 */
	protected function resolve( string $abstract, ?string $context = null ) {
		// Return cached instance if available.
		if ( isset( $this->instances[ $abstract ] ) ) {
			return $this->instances[ $abstract ];
		}

		// Check for contextual binding.
		if ( null !== $context && isset( $this->contextual[ $context ][ $abstract ] ) ) {
			$concrete = $this->contextual[ $context ][ $abstract ];

			if ( $concrete instanceof Closure ) {
				return $concrete( $this );
			}

			return $this->resolve( $concrete );
		}

		// Get the concrete type from bindings or fall back to the abstract itself.
		$concrete = $abstract;
		$shared   = false;

		if ( isset( $this->bindings[ $abstract ] ) ) {
			$concrete = $this->bindings[ $abstract ]['concrete'];
			$shared   = $this->bindings[ $abstract ]['shared'];
		}

		// If the concrete is a Closure, execute it.
		if ( $concrete instanceof Closure ) {
			$object = $concrete( $this );

			if ( $shared ) {
				$this->instances[ $abstract ] = $object;
			}

			return $object;
		}

		// Build the concrete class via reflection.
		$object = $this->build( $concrete );

		if ( $shared ) {
			$this->instances[ $abstract ] = $object;
		}

		return $object;
	}

	/**
	 * Instantiate a concrete class, auto-resolving constructor dependencies.
	 *
	 * @param string $concrete The fully-qualified class name.
	 *
	 * @return mixed
	 *
	 * @throws NotFoundException  If the class does not exist.
	 * @throws ContainerException On circular dependency or unresolvable parameter.
	 */
	protected function build( string $concrete ) {
		// Circular dependency detection.
		if ( isset( $this->resolving[ $concrete ] ) ) {
			$chain = implode( ' -> ', array_keys( $this->resolving ) );

			throw new ContainerException(
				esc_html(
					sprintf(
					/* translators: %s: dependency resolution chain */
						__( 'Circular dependency detected: %1$s -> %2$s', 'wpflint' ),
						$chain,
						$concrete
					)
				)
			);
		}

		$this->resolving[ $concrete ] = true;

		try {
			$reflector = new ReflectionClass( $concrete );
		} catch ( ReflectionException $e ) {
			unset( $this->resolving[ $concrete ] );

			throw new NotFoundException(
				esc_html(
					sprintf(
					/* translators: %s: class name */
						__( 'Class %s does not exist.', 'wpflint' ),
						$concrete
					)
				),
				0,
				$e // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Previous exception, not output.
			);
		}

		if ( ! $reflector->isInstantiable() ) {
			unset( $this->resolving[ $concrete ] );

			throw new ContainerException(
				esc_html(
					sprintf(
					/* translators: %s: class name */
						__( 'Class %s is not instantiable.', 'wpflint' ),
						$concrete
					)
				)
			);
		}

		$constructor = $reflector->getConstructor();

		if ( null === $constructor ) {
			unset( $this->resolving[ $concrete ] );

			return new $concrete();
		}

		$parameters   = $constructor->getParameters();
		$dependencies = $this->resolve_dependencies( $parameters, $concrete );

		unset( $this->resolving[ $concrete ] );

		return $reflector->newInstanceArgs( $dependencies );
	}

	/**
	 * Resolve constructor parameters.
	 *
	 * @param ReflectionParameter[] $parameters The constructor parameters.
	 * @param string                $concrete   The class being built (for contextual bindings).
	 *
	 * @return array<int, mixed> Resolved parameter values.
	 *
	 * @throws ContainerException If a parameter cannot be resolved.
	 */
	protected function resolve_dependencies( array $parameters, string $concrete ): array {
		$resolved = array();

		foreach ( $parameters as $parameter ) {
			$type = $parameter->getType();

			// If the parameter has a class type-hint, resolve it.
			if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
				$resolved[] = $this->resolve( $type->getName(), $concrete );
				continue;
			}

			// If the parameter has a default value, use it.
			if ( $parameter->isDefaultValueAvailable() ) {
				$resolved[] = $parameter->getDefaultValue();
				continue;
			}

			throw new ContainerException(
				esc_html(
					sprintf(
					/* translators: 1: parameter name, 2: class name */
						__( 'Unresolvable dependency [%1$s] in class %2$s.', 'wpflint' ),
						'$' . $parameter->getName(),
						$concrete
					)
				)
			);
		}

		return $resolved;
	}
}
