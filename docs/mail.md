# Mail

WPFlint's `Mail` class is a fluent builder over WordPress's `wp_mail()` function. It supports plain text, raw HTML, and PHP template bodies, plus CC, BCC, attachments, and custom headers.

## Basic Usage

```php
use WPFlint\Mail\Mail;

// Plain text:
Mail::to( 'user@example.com' )
    ->subject( 'Welcome to My Plugin!' )
    ->message( 'Thanks for signing up. Your account is ready.' )
    ->send();

// HTML body:
Mail::to( array( 'alice@example.com', 'bob@example.com' ) )
    ->subject( 'Order Confirmed' )
    ->html( '<h1>Your order has been confirmed.</h1><p>Thank you!</p>' )
    ->send();

// PHP template (requires View::set_base_path() to be configured):
Mail::to( $user->user_email )
    ->subject( 'Order Confirmed' )
    ->from( 'shop@example.com', 'My Shop' )
    ->template( 'emails.order-confirmed', array( 'order' => $order ) )
    ->send();
```

---

## API Reference

### `Mail::to( string|array $email ): self`

Static factory. Accepts a single email address string or an array of addresses.

```php
Mail::to( 'user@example.com' )
Mail::to( array( 'alice@example.com', 'bob@example.com' ) )
```

### `subject( string $subject ): self`

Set the email subject line.

### `message( string $message ): self`

Set a plain-text email body. Does not add any content-type header — use for plain text emails.

### `html( string $html ): self`

Set an HTML email body. Automatically adds `Content-Type: text/html; charset=UTF-8` header.

### `template( string $template, array $data = [] ): self`

Render a PHP template as the email body. Requires `View::set_base_path()` to be configured (or `ViewServiceProvider` to be registered). Automatically adds the HTML content-type header.

```php
->template( 'emails.order-confirmed', array( 'order' => $order, 'user' => $user ) )
```

Template file example (`resources/views/emails/order-confirmed.php`):

```php
<html>
<body>
    <h1>Order #<?php echo esc_html( $order->id ); ?> Confirmed</h1>
    <p>Hello <?php echo esc_html( $user->display_name ); ?>,</p>
    <p>Your order for <?php echo esc_html( number_format( $order->total, 2 ) ); ?> has been confirmed.</p>
</body>
</html>
```

### `from( string $email, string $name = '' ): self`

Set the From header.

```php
->from( 'noreply@example.com' )
->from( 'noreply@example.com', 'My Shop' )   // becomes: My Shop <noreply@example.com>
```

### `cc( string|array $email ): self`

Add Cc recipient(s). Accepts a single address or an array.

```php
->cc( 'manager@example.com' )
->cc( array( 'a@example.com', 'b@example.com' ) )
```

### `bcc( string|array $email ): self`

Add Bcc recipient(s).

### `header( string $header ): self`

Append a raw header string. Use for headers not covered by other methods.

```php
->header( 'Reply-To: support@example.com' )
->header( 'X-Custom-Header: value' )
```

### `attach( string $path ): self`

Attach a file by absolute server path. Multiple calls accumulate.

```php
->attach( '/var/www/uploads/invoice-42.pdf' )
->attach( '/var/www/uploads/receipt-42.pdf' )
```

### `send(): bool`

Sends the email via `wp_mail()`. Returns `true` on success, `false` on failure.

---

## Getters

```php
$mail = Mail::to( 'u@example.com' )
    ->subject( 'Hello' )
    ->message( 'World' );

$mail->get_to();          // ['u@example.com']
$mail->get_subject();     // 'Hello'
$mail->get_message();     // 'World'
$mail->get_headers();     // []
$mail->get_attachments(); // []
```

---

## Full Example

```php
use WPFlint\Mail\Mail;

function send_order_confirmation( int $order_id ): void {
    $order = Order::find( $order_id );
    $user  = get_userdata( $order->user_id );

    $result = Mail::to( $user->user_email )
        ->subject( sprintf( __( 'Order #%d Confirmed', 'my-shop' ), $order->id ) )
        ->from( get_option( 'admin_email' ), get_bloginfo( 'name' ) )
        ->bcc( get_option( 'admin_email' ) )
        ->template( 'emails.order-confirmed', array(
            'order' => $order,
            'user'  => $user,
        ) )
        ->attach( generate_invoice_pdf( $order->id ) )
        ->send();

    if ( ! $result ) {
        // log the failure
    }
}
```

---

## Notes

- `html()` and `template()` both set the `Content-Type: text/html` header. Calling both on the same instance results in two header lines — use only one.
- `wp_mail()` relies on the server's PHP mail configuration or a plugin like WP Mail SMTP for reliable delivery.
- Email addresses are passed directly to `wp_mail()` without extra validation — sanitize before calling `to()`.
