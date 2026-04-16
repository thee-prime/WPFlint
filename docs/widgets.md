# Widget Builder

WPFlint's `AbstractWidget` reduces the boilerplate of building a WordPress widget to three focused methods: `output()`, `fields()`, and optionally `sanitize()`.

## Creating a Widget

Extend `AbstractWidget` and implement the two required methods:

```php
use WPFlint\Widgets\AbstractWidget;

class RecentPostsWidget extends AbstractWidget {

    protected string $widget_title = 'Recent Posts';
    protected string $description  = 'Displays a configurable list of recent posts.';

    /**
     * Render the widget on the frontend.
     */
    protected function output( array $args, array $instance ): void {
        $title = ! empty( $instance['title'] ) ? $instance['title'] : 'Recent Posts';
        $count = ! empty( $instance['count'] ) ? (int) $instance['count'] : 5;

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['before_widget'];
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['before_title'] . esc_html( $title ) . $args['after_title'];

        $posts = get_posts( array( 'numberposts' => $count, 'post_status' => 'publish' ) );
        echo '<ul>';
        foreach ( $posts as $post ) {
            echo '<li><a href="' . esc_url( get_permalink( $post ) ) . '">'
                . esc_html( $post->post_title ) . '</a></li>';
        }
        echo '</ul>';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['after_widget'];
    }

    /**
     * Render the widget settings form in the admin.
     */
    protected function fields( array $instance ): void {
        $title = $instance['title'] ?? '';
        $count = $instance['count'] ?? 5;
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">Title:</label>
            <input class="widefat" type="text"
                id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                value="<?php echo esc_attr( $title ); ?>">
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>">Number of posts:</label>
            <input class="widefat" type="number" min="1" max="20"
                id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
                value="<?php echo esc_attr( $count ); ?>">
        </p>
        <?php
    }

    /**
     * Sanitise submitted settings before saving.
     */
    protected function sanitize( array $new_instance, array $old_instance ): array {
        return array(
            'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
            'count' => max( 1, min( 20, (int) ( $new_instance['count'] ?? 5 ) ) ),
        );
    }
}
```

## Registering a Widget

Call the static `register()` method — typically in a service provider's `boot()` or on the `widgets_init` hook:

```php
// In a service provider:
public function boot(): void {
    RecentPostsWidget::register();
}

// Or directly:
add_action( 'widgets_init', function () {
    RecentPostsWidget::register();
} );
```

`register()` hooks `register_widget( RecentPostsWidget::class )` onto `widgets_init` automatically.

---

## AbstractWidget API

### Properties to override in your subclass

| Property | Type | Description |
|----------|------|-------------|
| `$widget_title` | `string` | Human-readable name shown in the Widgets screen. |
| `$description` | `string` | Short description shown under the widget name. |

### Methods to implement

#### `output( array $args, array $instance ): void`

Renders the widget's visible HTML on the frontend.

- `$args` — Theme-supplied wrapper strings: `before_widget`, `after_widget`, `before_title`, `after_title`.
- `$instance` — Saved settings for this widget instance.

#### `fields( array $instance ): void`

Renders the widget settings form in the WordPress Widgets admin screen.

Use `$this->get_field_id( $name )` and `$this->get_field_name( $name )` to generate correct HTML `id` and `name` attributes — these handle multiple widget instances on the same page correctly.

#### `sanitize( array $new_instance, array $old_instance ): array` *(optional)*

Sanitises and returns the new settings before WordPress saves them. Called automatically by `update()`.

The default implementation applies `sanitize_text_field()` to all string values. Override for custom logic.

### `static register(): void`

Hooks `register_widget( static::class )` to `widgets_init`. Call once per widget class.

### ID Base

The widget's `id_base` is automatically derived from the class short name converted to snake_case:

| Class name | id_base |
|------------|---------|
| `RecentPostsWidget` | `recent_posts_widget` |
| `PricingTableWidget` | `pricing_table_widget` |
| `CTAWidget` | `c_t_a_widget` |

---

## WP_Widget Bridge

`AbstractWidget` extends `\WP_Widget` and seals the three WP_Widget methods so subclasses use the cleaner API:

| WP_Widget method | Delegates to |
|------------------|-------------|
| `widget( $args, $instance )` | `output( array, array )` |
| `form( $instance )` | `fields( array )` |
| `update( $new, $old )` | `sanitize( array, array )` |

All three parent methods are declared `final` in `AbstractWidget` — do not override them; implement the delegate methods instead.

---

## Full Example with Multiple Instances

```php
namespace MyPlugin\Widgets;

use WPFlint\Widgets\AbstractWidget;

class TestimonialWidget extends AbstractWidget {

    protected string $widget_title = 'Testimonial';
    protected string $description  = 'Displays a customer testimonial quote.';

    protected function output( array $args, array $instance ): void {
        $quote  = $instance['quote']  ?? '';
        $author = $instance['author'] ?? '';

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['before_widget'];
        if ( $quote ) {
            echo '<blockquote class="testimonial">';
            echo '<p>' . esc_html( $quote ) . '</p>';
            if ( $author ) {
                echo '<cite>' . esc_html( $author ) . '</cite>';
            }
            echo '</blockquote>';
        }
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $args['after_widget'];
    }

    protected function fields( array $instance ): void {
        $quote  = $instance['quote']  ?? '';
        $author = $instance['author'] ?? '';
        ?>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'quote' ) ); ?>">Quote:</label>
            <textarea class="widefat" rows="4"
                id="<?php echo esc_attr( $this->get_field_id( 'quote' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'quote' ) ); ?>"
            ><?php echo esc_textarea( $quote ); ?></textarea>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'author' ) ); ?>">Author:</label>
            <input class="widefat" type="text"
                id="<?php echo esc_attr( $this->get_field_id( 'author' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'author' ) ); ?>"
                value="<?php echo esc_attr( $author ); ?>">
        </p>
        <?php
    }

    protected function sanitize( array $new_instance, array $old_instance ): array {
        return array(
            'quote'  => sanitize_textarea_field( $new_instance['quote']  ?? '' ),
            'author' => sanitize_text_field( $new_instance['author'] ?? '' ),
        );
    }
}
```

Register in a service provider:

```php
TestimonialWidget::register();
```
