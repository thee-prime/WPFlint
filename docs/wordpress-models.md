# WordPress Models

WPFlint ships pre-built Model classes for every core WordPress database table.  
They live under the `WPFlint\WordPress` namespace and can be used immediately
without writing any migration or schema — the tables already exist.

## Available Models

| Class | Table | Primary Key | Notes |
|---|---|---|---|
| `Post` | `{prefix}posts` | `ID` | Scopes + relationships |
| `PostMeta` | `{prefix}postmeta` | `meta_id` | Belongs to Post |
| `User` | `{prefix}users` | `ID` | `user_pass` hidden |
| `UserMeta` | `{prefix}usermeta` | `umeta_id` | Belongs to User |
| `Comment` | `{prefix}comments` | `comment_ID` | Scopes + relationships |
| `CommentMeta` | `{prefix}commentmeta` | `meta_id` | Belongs to Comment |
| `Term` | `{prefix}terms` | `term_id` | Relationships to TermTaxonomy |
| `TermTaxonomy` | `{prefix}term_taxonomy` | `term_taxonomy_id` | Scope: in_taxonomy |
| `TermRelationship` | `{prefix}term_relationships` | `object_id`* | Pivot table |
| `Option` | `{prefix}options` | `option_id` | Scopes: autoloaded / not_autoloaded |
| `Link` | `{prefix}links` | `link_id` | Scope: visible |

> \* `term_relationships` uses a composite primary key in the database. The model
> treats `object_id` as the primary key for query building; use explicit
> `where()` clauses when you need to target a specific row.

All WordPress models set `$timestamps = false` because WordPress uses its own
date columns (`post_date`, `user_registered`, etc.) rather than `created_at` /
`updated_at`.

---

## Basic Usage

```php
use WPFlint\WordPress\Post;
use WPFlint\WordPress\User;
use WPFlint\WordPress\Comment;
use WPFlint\WordPress\Term;
use WPFlint\WordPress\TermTaxonomy;
use WPFlint\WordPress\Option;

// Find a post by ID.
$post = Post::find( 42 );
echo $post->post_title;

// All published posts of type 'product'.
$products = Post::published()->type( 'product' )->get_models();

// Current user.
$user = User::find( get_current_user_id() );
// user_pass and user_activation_key are automatically hidden from to_array().

// Approved comments on a post.
$comments = Comment::approved()
    ->where( 'comment_post_ID', $post_id )
    ->get_models();

// All categories (taxonomy = 'category').
$categories = TermTaxonomy::in_taxonomy( 'category' )->get_models();

// Autoloaded options.
$options = Option::autoloaded()->get_models();
```

---

## Scopes

### Post

| Scope | SQL equivalent |
|---|---|
| `Post::published()` | `WHERE post_status = 'publish'` |
| `Post::draft()` | `WHERE post_status = 'draft'` |
| `Post::type('product')` | `WHERE post_type = 'product'` |
| `Post::status('trash')` | `WHERE post_status = 'trash'` |

### Comment

| Scope | SQL equivalent |
|---|---|
| `Comment::approved()` | `WHERE comment_approved = '1'` |
| `Comment::pending()` | `WHERE comment_approved = '0'` |
| `Comment::spam()` | `WHERE comment_approved = 'spam'` |
| `Comment::type('pingback')` | `WHERE comment_type = 'pingback'` |

### TermTaxonomy

| Scope | SQL equivalent |
|---|---|
| `TermTaxonomy::in_taxonomy('category')` | `WHERE taxonomy = 'category'` |

### Option

| Scope | SQL equivalent |
|---|---|
| `Option::autoloaded()` | `WHERE autoload = 'yes'` |
| `Option::not_autoloaded()` | `WHERE autoload = 'no'` |

### Link

| Scope | SQL equivalent |
|---|---|
| `Link::visible()` | `WHERE link_visible = 'Y'` |

---

## Relationships

```php
// Post → author (User).
$author = $post->author()->first_model();

// Post → meta entries (PostMeta collection).
$meta = $post->meta()->get_models();

// Post → comments.
$comments = $post->comments()->get_models();

// Post → parent post.
$parent = $post->parent_post()->first_model();

// User → posts.
$posts = $user->posts()->where( 'post_status', 'publish' )->get_models();

// User → user meta.
$usermeta = $user->meta()->where( 'meta_key', 'billing_city' )->first_model();

// Comment → post.
$post = $comment->post()->first_model();

// Term → taxonomy records.
$taxonomies = $term->taxonomies()->get_models();

// TermTaxonomy → relationships (pivot rows).
$relationships = $termTaxonomy->relationships()->get_models();
```

---

## Extending WordPress Models

Override any WordPress model to add custom scopes, relationships, or business
logic. The extended class automatically inherits the correct table name and
primary key.

```php
// app/Models/Product.php
use WPFlint\WordPress\Post;
use WPFlint\Database\ORM\ModelQueryBuilder;

class Product extends Post {

    /**
     * Scope: active products only.
     */
    public function scope_active( ModelQueryBuilder $q ): ModelQueryBuilder {
        return $q->where( 'post_type', '=', 'product' )
                 ->where( 'post_status', '=', 'publish' );
    }

    /**
     * Related price meta.
     */
    public function price_meta() {
        return $this->has_one( \WPFlint\WordPress\PostMeta::class, 'post_id', 'ID' )
                    ->where( 'meta_key', '_price' );
    }
}

// Usage:
$products = Product::active()->order_by( 'post_date', 'DESC' )->paginate( 20 );
```

> **Tip:** Registering the model in a ServiceProvider keeps your plugin
> organised and makes the extended class available via the container.

---

## Extending User for Custom Roles

```php
// app/Models/Admin.php
use WPFlint\WordPress\User;
use WPFlint\Database\ORM\ModelQueryBuilder;

class Admin extends User {

    public function scope_admins( ModelQueryBuilder $q ): ModelQueryBuilder {
        return $this->scope_role( $q, 'administrator' );
    }
}

$admins = Admin::admins()->get_models();
```

---

## Notes

- All WordPress models disable automatic `created_at` / `updated_at` timestamps
  (`$timestamps = false`). Date columns like `post_date` and `user_registered`
  are managed by WordPress itself.
- `User::$hidden` includes `user_pass` and `user_activation_key`, so they are
  never included in `to_array()` or `to_json()` output.
- For `term_relationships`, prefer `where('object_id', $post_id)` over `find()`
  because the table has a composite primary key.
- Use `get_option()` / `update_option()` for simple reads and writes to the
  `options` table. The `Option` model is most useful for bulk queries.
