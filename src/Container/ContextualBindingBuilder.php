<?php
/**
 * Contextual binding builder for the IoC container.
 *
 * @package WPFlint\Container
 */

declare(strict_types=1);

namespace WPFlint\Container;

/**
 * Fluent builder for contextual bindings: when(A)->needs(B)->give(C).
 */
class ContextualBindingBuilder {

	/**
	 * The container instance.
	 *
	 * @var Container
	 */
	protected Container $container;

	/**
	 * The concrete class that needs the contextual binding.
	 *
	 * @var string
	 */
	protected string $concrete;

	/**
	 * The abstract that should be replaced.
	 *
	 * @var string
	 */
	protected string $needs;

	/**
	 * Create a new contextual binding builder.
	 *
	 * @param Container $container The container instance.
	 * @param string    $concrete  The concrete class name.
	 */
	public function __construct( Container $container, string $concrete ) {
		$this->container = $container;
		$this->concrete  = $concrete;
	}

	/**
	 * Define the abstract target for this contextual binding.
	 *
	 * @param string $abstract The interface or class being depended on.
	 *
	 * @return $this
	 */
	public function needs( string $abstract ): self {
		$this->needs = $abstract;

		return $this;
	}

	/**
	 * Define the implementation to provide.
	 *
	 * @param mixed $implementation Class name or closure.
	 *
	 * @return void
	 */
	public function give( $implementation ): void {
		$this->container->addContextualBinding( $this->concrete, $this->needs, $implementation );
	}
}
