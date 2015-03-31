<?php

/**
 * Plugin Name: TW Pagination
 * Plugin URI: http://vuckovic.biz/wordpress-plugins/tw-pagination
 * Description: A simple and flexible pagination plugin for WordPress posts and comments.
 * Author: Igor Vučković
 * Author URI: http://vuckovic.biz
 * Version: 1.1
 */

//	Set the wp-content and plugin urls/paths
if(!defined('WP_CONTENT_URL'))
    define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
if(!defined('WP_CONTENT_DIR'))
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
if(!defined('WP_PLUGIN_URL'))
    define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');
if(!defined('WP_PLUGIN_DIR'))
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');

if(!class_exists('TW_Pagination')) {
    class TW_Pagination
    {
        //	@var string(The plugin version)		
        var $version = '1.0';
        //	@var string(The options string name for this plugin)		
        var $options_name = 'tw_pagination_options';
        //	@var string $localizationDomain(Domain used for localization)
        var $localization_domain = 'tw-pagination';
        //	@var string(The url to this plugin)
        var $pluginurl = '';
        //	@var string(The path to this plugin)		
        var $pluginpath = '';
        //	@var array $options(Stores the options for this plugin)
        var $options = array();
        //	@var string $type(The post type)
        var $type = 'posts';

        /**
         * Constructor
         */
        public function __construct()
        {
            $name = dirname(plugin_basename(__FILE__));
            
            //Setup constants
            $this->pluginurl = WP_PLUGIN_URL . "/$name/";
            $this->pluginpath = WP_PLUGIN_DIR . "/$name/";

            //Initialize the options
            $this->get_options();

            //Actions
            add_action('admin_menu', array(&$this, 'admin_menu_link'));

            if($this->options['css']) {
                add_action('wp_print_styles', array(&$this, 'tw_pagination_css'));
            }
        }

        /**
         * @param string $query
         * @param bool $args
         */
        public function paginate($query = 'global', $args = false)
        {
            if($this->type === 'comments' && !get_option('page_comments'))
                return;

            $ar = wp_parse_args($args, $this->options);
            extract($ar, EXTR_SKIP);

            if(!isset($page) && !isset($pages)) {
                if($query !== 'global' && is_object($query)) {
                    $wp_query = $query;
                } else {
                    global $wp_query;
                }

                if($this->type === 'posts') {
                    $page = get_query_var('paged');
                    $pages = $wp_query->max_num_pages;
                } else {
                    $page = get_query_var('cpage');
                    $comments_per_page = get_option('comments_per_page');
                    $pages = get_comment_pages_count();
                }

                $page = !empty($page) ? intval($page) : 1;
            }

            $prev_link =($this->type === 'posts') ? esc_url(get_pagenum_link($page - 1)) : get_comments_pagenum_link($page - 1);
            $next_link =($this->type === 'posts') ? esc_url(get_pagenum_link($page + 1)) : get_comments_pagenum_link($page + 1);

            $output = stripslashes($before);

            if($pages > 1) {
                $output .= sprintf('<ol class="tw-pagination%s">',($this->type === 'posts') ? '' : ' tw-pagination-comments');
                $output .= sprintf('<li><span class="title">%s</span></li>', stripslashes($title));
                $ellipsis = "<li><span class='gap'>...</span></li>";

                if($page > 1 && !empty($previouspage)) {
                    $output .= sprintf('<li><a href="%s" class="prev">%s</a></li>', $prev_link, stripslashes($previouspage));
                }

                $min_links = $range * 2 + 1;
                $block_min = min($page - $range, $pages - $min_links);
                $block_high = max($page + $range, $min_links);
                $left_gap =(($block_min - $anchor - $gap) > 0) ? true : false;
                $right_gap =(($block_high + $anchor + $gap) < $pages) ? true : false;

                if($left_gap && !$right_gap) {
                    $output .= sprintf('%s%s%s', $this->paginate_loop(1, $anchor), $ellipsis, $this->paginate_loop($block_min, $pages, $page));
                } else if($left_gap && $right_gap) {
                    $output .= sprintf('%s%s%s%s%s', $this->paginate_loop(1, $anchor), $ellipsis, $this->paginate_loop($block_min, $block_high, $page), $ellipsis, $this->paginate_loop(($pages - $anchor + 1), $pages));
                } else if($right_gap && !$left_gap) {
                    $output .= sprintf('%s%s%s', $this->paginate_loop(1, $block_high, $page), $ellipsis, $this->paginate_loop(($pages - $anchor + 1), $pages));
                } else {
                    $output .= $this->paginate_loop(1, $pages, $page);
                }

                if($page < $pages && !empty($nextpage)) {
                    $output .= sprintf('<li><a href="%s" class="next">%s</a></li>', $next_link, stripslashes($nextpage));
                }

                $output .= "</ol>";
            }

            $output .= stripslashes($after);

            if($pages > 1 || $empty) {
                echo $output;
            }
        }

        /**
         * Helper function for pagination which builds the page links.
         *
         * @param int $start
         * @param int $max
         * @param int $page
         * @return string
         */
        public function paginate_loop($start, $max, $page = 0)
        {
            $output = "";

            for ($i = $start; $i <= $max; $i++) {
                $p = ($this->type === 'posts') ? esc_url(get_pagenum_link($i)) : get_comments_pagenum_link($i);
                $output .= ($page == intval($i)) ? "<li><span class='page current'>$i</span></li>" : "<li><a href='$p' title='$i' class='page'>$i</a></li>";
            }

            return $output;
        }

        /**
         * Inserts CSS style
         */
        public function tw_pagination_css()
        {
            $name = "tw-pagination.css";

            if (false !== file_exists(TEMPLATEPATH . "/$name")) {
                $css = get_template_directory_uri() . "/$name";
            } else {
                $css = $this->pluginurl . $name;
            }

            wp_enqueue_style('tw-pagination', $css, false, $this->version, 'screen');
        }

        /**
         * Retrieves the plugin options from the database
         */
        public function get_options()
        {
            if (!$options = get_option($this->options_name)) {

                $options = array(
                    'title' => __('Pages:', $this->localization_domain),
                    'nextpage' => '&raquo;',
                    'previouspage' => '&laquo;',
                    'css' => true,
                    'before' => '<div class="navigation">',
                    'after' => '</div>',
                    'empty' => true,
                    'range' => 3,
                    'anchor' => 1,
                    'gap' => 3
                );

                update_option($this->options_name, $options);
            }

            $this->options = $options;
        }

        /**
         * Saves the admin options to the database.
         * @return mixed
         */
        public function save_admin_options()
        {
            return update_option($this->options_name, $this->options);
        }

        /**
         *Adds the options subpanel
         */
        public function admin_menu_link()
        {
            add_options_page('TW Pagination', 'TW Pagination', 'manage_options', basename(__FILE__), array(&$this, 'admin_options_page'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filter_plugin_actions'), 10, 2);
        }

        /*
         * Adds the Settings link to the plugin activate/deactivate page
         */
        public function filter_plugin_actions($links, $file)
        {
            $settings_link = '<a href="options-general.php?page=' . basename(__FILE__) . '">' . __('Settings', $this->localization_domain) . '</a>';
            array_unshift($links, $settings_link);

            return $links;
        }

		/**
		 * Adds settings/options page
		 */
        public function admin_options_page()
        {
            if (isset($_POST['tw_pagination_save'])) {
                if (wp_verify_nonce($_POST['_wpnonce'], 'tw-pagination-update-options')) {
                    $this->options['title'] = $_POST['title'];
                    $this->options['previouspage'] = $_POST['previouspage'];
                    $this->options['nextpage'] = $_POST['nextpage'];
                    $this->options['before'] = $_POST['before'];
                    $this->options['after'] = $_POST['after'];
                    $this->options['empty'] = (isset($_POST['empty']) && $_POST['empty'] === 'on') ? true : false;
                    $this->options['css'] = (isset($_POST['css']) && $_POST['css'] === 'on') ? true : false;
                    $this->options['range'] = intval($_POST['range']);
                    $this->options['anchor'] = intval($_POST['anchor']);
                    $this->options['gap'] = intval($_POST['gap']);

                    $this->save_admin_options();

                    echo '<div class="updated"><p>' . __('Success! Your changes were successfully saved!', $this->localization_domain) . '</p></div>';
                } else {
                    echo '<div class="error"><p>' . __('Whoops! There was a problem with the data you posted. Please try again.', $this->localization_domain) . '</p></div>';
                }
            }
?>

<div class="wrap">
	<div class="icon32" id="icon-options-general"><br/></div>
	<h2>TW Pagination</h2>
	<form method="post" id="tw_pagination_options">	
	<?php wp_nonce_field('tw-pagination-update-options'); ?>	
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Pagination Label:', $this->localization_domain); ?></th>
				<td><input name="title" type="text" id="title" size="40" value="<?php echo stripslashes(htmlspecialchars($this->options['title'])); ?>"/>
				<span class="description"><?php _e('The text/HTML to display before the list of pages.', $this->localization_domain); ?></span></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Previous Page:', $this->localization_domain); ?></th>
				<td><input name="previouspage" type="text" id="previouspage" size="40" value="<?php echo stripslashes(htmlspecialchars($this->options['previouspage'])); ?>"/>
				<span class="description"><?php _e('The text/HTML to display for the previous page link.', $this->localization_domain); ?></span></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Next Page:', $this->localization_domain); ?></th>
				<td><input name="nextpage" type="text" id="nextpage" size="40" value="<?php echo stripslashes(htmlspecialchars($this->options['nextpage'])); ?>"/>
				<span class="description"><?php _e('The text/HTML to display for the next page link.', $this->localization_domain); ?></span></td>
			</tr>
		</table>
		<p>&nbsp;</p>	
		<h3><?php _e('Advanced Settings', $this->localization_domain); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('Before Markup:', $this->localization_domain); ?></th>
				<td><input name="before" type="text" id="before" size="40" value="<?php echo stripslashes(htmlspecialchars($this->options['before'])); ?>"/>
				<span class="description"><?php _e('The HTML markup to display before the pagination code.', $this->localization_domain); ?></span></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('After Markup:', $this->localization_domain); ?></th>
				<td><input name="after" type="text" id="after" size="40" value="<?php echo stripslashes(htmlspecialchars($this->options['after'])); ?>"/>
				<span class="description"><?php _e('The HTML markup to display after the pagination code.', $this->localization_domain); ?></span></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Markup Display:', $this->localization_domain); ?></th>
				<td><label for="empty">
					<input type="checkbox" id="empty" name="empty" <?php echo($this->options['empty'] === true) ? "checked='checked'" : ""; ?>/> <?php _e('Show Before Markup and After Markup, even if the page list is empty?', $this->localization_domain); ?></label></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('TW-Pagination CSS File:', $this->localization_domain); ?></th>
				<td><label for="css">
					<input type="checkbox" id="css" name="css" <?php echo($this->options['css'] === true) ? "checked='checked'" : ""; ?>/> <?php printf(__('Include the default stylesheet tw-pagination.css? TW-Pagination will first look for <code>tw-pagination.css</code> in your theme directory(<code>themes/%s</code>).', $this->localization_domain), get_template()); ?></label></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Page Range:', $this->localization_domain); ?></th>
				<td>
					<select name="range" id="range">	
					<?php for($i=1; $i<=10; $i++) : ?>	
						<option value="<?php echo $i; ?>" <?php echo($i == $this->options['range']) ? "selected='selected'" : ""; ?>><?php echo $i; ?></option>	
					<?php endfor; ?>	
					</select>
					<span class="description"><?php _e('The number of page links to show before and after the current page. Recommended value: 3', $this->localization_domain); ?></span></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Page Anchors:', $this->localization_domain); ?></th>
				<td>
					<select name="anchor" id="anchor">	
					<?php for($i=1; $i<=10; $i++) : ?>	
						<option value="<?php echo $i; ?>" <?php echo($i == $this->options['anchor']) ? "selected='selected'" : ""; ?>><?php echo $i; ?></option>	
					<?php endfor; ?>	
					</select>
					<span class="description"><?php _e('The number of links to always show at beginning and end of pagination. Recommended value: 1', $this->localization_domain); ?></span></td>
			</tr>
			<tr valign="top">
				<th scope="row"><?php _e('Page Gap:', $this->localization_domain); ?></th>
				<td>
					<select name="gap" id="gap">	
					<?php for($i=1; $i<=10; $i++) : ?>	
						<option value="<?php echo $i; ?>" <?php echo($i == $this->options['gap']) ? "selected='selected'" : ""; ?>><?php echo $i; ?></option>	
					<?php endfor; ?>	
					</select>
					<span class="description"><?php _e('The minimum number of pages in a gap before an ellipsis(...) is added. Recommended value: 3', $this->localization_domain); ?></span></td>
			</tr>
		</table>
		<p>&nbsp;</p>
		<h3><?php _e('Implement', $this->localization_domain); ?></h3>
		<p><strong><?php _e('For posts pagination:', $this->localization_domain)?></strong></p>
		<p><?php printf(__('1. Open the theme files where you\'d like pagination to be used. Usually this is the <code>themes/%s/loop.php</code> file.', $this->localization_domain), get_template()); ?></p>
		<p><?php _e('2. Replace your existing <code>previous_posts_link()</code> and <code>next_posts_link()</code> code block with the following:', $this->localization_domain); ?></p>
		<p><code>&lt;?php if(function_exists(&#39;tw_pagination&#39;)) tw_pagination(); ?&gt;</code></p>
		<p><strong><?php _e('For comments pagination:', $this->localization_domain)?></strong><p>
		<p><?php printf(__('1. Open the theme file(s) where you\'d like comments pagination to be used. Usually this is the <code>themes/%s/comments.php</code> file.', $this->localization_domain), get_template()); ?></p>
		<p><?php _e('2. Replace your existing <code>previous_comments_link()</code> and <code>next_comments_link()</code> code block with the following:', $this->localization_domain); ?></p>
		<p><code>&lt;?php if(function_exists(&#39;tw_pagination_comments&#39;)) tw_pagination_comments(); ?&gt;</code></p>
		<p><strong><?php _e('Note:', $this->localization_domain)?></strong></p>
		<p><?php _e('If you want to initialise new custom <code>WP_Query</code> object and want to paginate your query, than you should call <code>tw_pagination()</code> function by passing your instance as first argument.', $this->localization_domain)?></p>
		<p><?php _e('For example, if you initialise new query like this <code>&lt;?php $the_query = new WP_Query( $args ); ?&gt;</code> than you should call <code>tw_pagination()</code> function like this: <code>&lt;?php if(function_exists(&#39;tw_pagination&#39;)) tw_pagination($the_query); ?&gt;</code>', $this->localization_domain)?></p>
		<p class="submit"><input type="submit" value="Save Changes" name="tw_pagination_save" class="button-primary" /></p>
	</form>
</div>

<?php
		}
	}
}

//	Instantiate the class
if (class_exists('TW_Pagination')) {
    $tw_pagination = new TW_Pagination();
}

//	Pagination function to use for posts
if (!function_exists('tw_pagination')) {
    function tw_pagination($query = 'global', $args = false)
    {
        global $tw_pagination;
        return $tw_pagination->paginate($query, $args);
    }
}

//	Pagination function to use for post comments
if (!function_exists('tw_pagination_comments')) {
    function tw_pagination_comments($query = 'global', $args = false)
    {
        global $tw_pagination;
        $tw_pagination->type = 'comments';
        return $tw_pagination->paginate($query, $args);
    }
}

?>