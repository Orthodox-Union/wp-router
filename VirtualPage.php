<?php
function virtualPage($title,$slug) {
    $createPost = function() use ($title,$slug) {
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
        $wp_query->is_home = FALSE;
        $wp_query->is_archive = FALSE;
        $wp_query->is_category = FALSE;
        unset($wp_query->query['error']);
        $wp_query->query_vars['error'] = '';
        $wp_query->is_404 = FALSE;
        
        return ($posts);
    });
}