<?php

namespace wpRouter;

class Route
{
    private string $route;
    private string $title;
    private string $content;
    private int $author;
    private string $date;
    private string $type;
    private array $vars;
    private ?string $template;
    private string $routeType;
    private $handler;
    private bool $ssl;

    private static bool $routeFound = false;

    /**
     * Route constructor.
     *
     * @param array $args
     */
    public function __construct(array $args)
    {
        $this->route = sanitize_text_field($args['route']);
        $this->title = isset($args['title']) ? sanitize_text_field($args['title']) : '';
        $this->content = isset($args['content']) ? wp_kses_post($args['content']) : '';
        $this->author = isset($args['author']) ? (int)$args['author'] : 1;
        $this->date = isset($args['date']) ? sanitize_text_field($args['date']) : current_time('mysql');
        $this->type = isset($args['type']) ? sanitize_text_field($args['type']) : 'page';
        $this->vars = isset($args['vars']) ? array_map('sanitize_text_field', $args['vars']) : [];
        $this->template = isset($args['template']) ? sanitize_text_field($args['template']) : null;
        $this->routeType = isset($args['routeType']) ? sanitize_text_field($args['routeType']) : 'static';
        $this->handler = $args['handler'];
        $this->ssl = isset($args['ssl']) ? (bool)$args['ssl'] : false;

        $this->setupRewrite();
    }

    /**
     * Set up the rewrite rules for the route.
     */
    private function setupRewrite(): void
    {
        add_action('parse_request', function (&$wp) {
            if (self::$routeFound) {
                return;
            }

            $matches = [$this->route];
            $routeMatches = $this->routeType === 'static'
                ? $this->route === $wp->request
                : preg_match('#' . $this->route . '#', $wp->request, $matches);

            if ($routeMatches) {
                if ($this->ssl && !$this->isSsl()) {
                    wp_redirect(esc_url('https://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']));
                    exit;
                }

                self::$routeFound = true;
                $wp->query_vars = call_user_func($this->vars, $matches);

                if ($this->template) {
                    add_filter('template_include', fn() => locate_template($this->template, false));
                }

                call_user_func($this->handler, $wp->query_vars);
            }
        });
    }

    /**
     * Check if the request is made over SSL.
     *
     * @return bool
     */
    private function isSsl(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' || $_SERVER['SERVER_PORT'] == 443;
    }
}

/**
 * Create a virtual page.
 *
 * @param string $title
 * @param string|null $slug
 * @param \stdClass|null $post
 */
function virtualPage(string $title, ?string $slug = null, ?\stdClass $post = null): void
{
    $slug = $slug ? sanitize_title($slug) : sanitize_title(trim($_SERVER['REQUEST_URI'], '/'));

    $createPost = static function () use ($title, $slug, $post): \stdClass {
        if ($post === null) {
            $post = new \stdClass();
            $post->ID = -1;
            $post->post_title = sanitize_text_field($title);
            $post->post_excerpt = '';
            $post->post_status = 'publish';
            $post->comment_status = 'closed';
            $post->ping_status = 'closed';
            $post->post_password = '';
            $post->post_name = $slug;
            $post->to_ping = '';
            $post->pinged = '';
            $post->post_type = 'page';
            $post->post_mime_type = '';
            $post->comment_count = 0;
        }

        return $post;
    };

    add_filter('the_posts', static function ($posts) use ($createPost) {
        global $wp_query;

        static $count = 0;
        if ($count++) {
            return $posts;
        }

        $post = $createPost();

        $posts = [$post];

        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_home = $wp_query->query['is_home'] ?? false;
        $wp_query->is_archive = false;
        $wp_query->is_category = false;
        unset($wp_query->query['error']);
        $wp_query->query_vars['error'] = '';
        $wp_query->is_404 = false;

        return $posts;
    });
}
