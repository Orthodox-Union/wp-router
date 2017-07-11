<?php

class Route
{
    private $route = NULL;
    private $title = NULL;
    private $content = NULL;
    private $author = NULL;
    private $date = NULL;
    private $type = NULL;
    
    static $routeFound = false;

    public function __construct($args)
    {
        $this->route = $args['route'];
        $this->title = isset($args['title']) ? $args['title'] : '';
        $this->content = isset($args['content']) ? $args['content'] : '';
        $this->author = isset($args['author']) ? $args['author'] : 1;
        $this->date = isset($args['date']) ? $args['date'] : current_time('mysql');
        $this->dategmt = isset($args['date']) ? $args['date'] : current_time('mysql', 1);
        $this->type = isset($args['type']) ? $args['type'] : 'page';
        $this->vars = $args['vars'];
        $this->template = isset($args['template']) ? $args['template'] : false;
        $this->routeType = isset($args['routeType']) ? $args['routeType'] : 'static';
        $this->handler = $args['handler'];
        $this->ssl = isset($args['ssl']) ? $args['ssl'] : false;

        $this->setupRewrite();
    }

    private function setupRewrite() {
        $handler = $this->handler;
        $vars = $this->vars;
        $route = $this->route;
        $that = $this;
        
        add_action('parse_request', function (&$wp) use ($handler,$route,$vars,$that)
        {
            if (Route::$routeFound) return;

            $matches = array($route);
            $routeMatches = $that->routeType == 'static'
                ? $route == $wp->request
                : preg_match('#'.$route.'#',$wp->request,$matches);
            
            if ($routeMatches) {
                if ($that->ssl) {
                    $using_ssl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on' || $_SERVER['SERVER_PORT'] == 443;

                    if (!$using_ssl) {
                        header('Location: https://' . $_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI']);die;
                    }
                }

                Route::$routeFound = true;
                $wp->query_vars = $vars($matches);
                
                if ($that->template) {
                    add_filter( 'template_include',function() use ($that) {
                        return locate_template($that->template,false);
                    });
                }
       
                $handler($wp->query_vars);
            }

            return;
        });
        
    }
}

function virtualPage($title, $slug = null, $post = null) {
    $slug = $slug ?: trim($_SERVER['REQUEST_URI'], '/');

    $createPost = function() use ($title, $slug, $post) {
        if ($post === null) {
            $post = new stdClass;

            // fill properties of $post with everything a page in the database would have
            $post->ID = -1;                           // use an illegal value for page ID
            $post->post_title = $title;
            $post->post_excerpt = '';
            $post->post_status = 'publish';
            $post->comment_status = 'closed';        // mark as closed for comments, since page doesn't exist
            $post->ping_status = 'closed';           // mark as closed for pings, since page doesn't exist
            $post->post_password = '';               // no password
            $post->post_name = $slug;
            $post->to_ping = '';
            $post->pinged = '';
            $post->post_type = 'page';
            $post->post_mime_type = '';
            $post->comment_count = 0;
        }

        return $post;
    };

    add_filter('the_posts', function($posts) use ($createPost) {
        global $wp_query;
        // Make sure this is only called once, so it doesn't screw up additional calls to wp_query
        static $count=0;
        if ($count++) return $posts;

        $post = $createPost();

        // set filter results
        $posts = array($post);

        // reset wp_query properties to simulate a found page
        $wp_query->is_page = TRUE;
        $wp_query->is_singular = TRUE;
        $wp_query->is_home = isset($wp_query->query['is_home']) ? $wp_query->query['is_home'] : false;
        $wp_query->is_archive = FALSE;
        $wp_query->is_category = FALSE;
        unset($wp_query->query['error']);
        $wp_query->query_vars['error'] = '';
        $wp_query->is_404 = FALSE;
        
        return ($posts);
    });
}
