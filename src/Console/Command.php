<?php
/**
 * Base WP-CLI command class.
 *
 * Dev-only — excluded via .distignore, never autoloaded in prod.
 *
 * @package WPFlint\Console
 */

declare(strict_types=1);

namespace WPFlint\Console;

/**
 * Base class for all WP-CLI commands.
 *
 * Provides convenience wrappers around WP_CLI output methods.
 */
abstract class Command {

	/**
	 * Output an informational line.
	 *
	 * @param string $message Message text.
	 * @return void
	 */
	protected function info( string $message ): void {
		\WP_CLI::line( $message );
	}

	/**
	 * Output a success message.
	 *
	 * @param string $message Message text.
	 * @return void
	 */
	protected function success( string $message ): void {
		\WP_CLI::success( $message );
	}

	/**
	 * Output an error and halt execution.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	protected function error( string $message ): void {
		\WP_CLI::error( $message );
	}

	/**
	 * Output a warning.
	 *
	 * @param string $message Warning message.
	 * @return void
	 */
	protected function warning( string $message ): void {
		\WP_CLI::warning( $message );
	}

	/**
	 * Prompt the user for confirmation.
	 *
	 * @param string $message Confirmation prompt.
	 * @return void
	 */
	protected function confirm( string $message ): void {
		\WP_CLI::confirm( $message );
	}

	/**
	 * Output a formatted table.
	 *
	 * @param array $headers Column headers.
	 * @param array $rows    Row data (array of arrays).
	 * @return void
	 */
	protected function table( array $headers, array $rows ): void {
		$formatter = new \WP_CLI\Formatter(
			(object) array( 'format' => 'table' ),
			$headers
		);
		$formatter->display_items( $rows );
	}

	/**
	 * Create a progress bar.
	 *
	 * @param string $message Progress label.
	 * @param int    $count   Total items.
	 * @return object Progress bar instance.
	 */
	protected function progress( string $message, int $count ) {
		return \WP_CLI\Utils\make_progress_bar( $message, $count );
	}

	/**
	 * Convert a PascalCase string to snake_case.
	 *
	 * @param string $value Input string.
	 * @return string
	 */
	protected function snake_case( string $value ): string {
		$value = preg_replace( '/([a-z])([A-Z])/', '$1_$2', $value );
		return strtolower( $value );
	}

	/**
	 * Write a file and report success.
	 *
	 * @param string $filepath Full path to file.
	 * @param string $content  File content.
	 * @return void
	 */
	protected function write_file( string $filepath, string $content ): void {
		$directory = dirname( $filepath );

		if ( ! is_dir( $directory ) ) {
			wp_mkdir_p( $directory );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI dev tool, not production code.
		file_put_contents( $filepath, $content );

		$this->success(
			sprintf(
				/* translators: %s: file path */
				__( 'Created: %s', 'wpflint' ),
				$filepath
			)
		);
	}
}
