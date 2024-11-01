<?
/*
Plugin Name: Zamango Money Extractor
Plugin URI: http://www.zamango.com/
Description: Creates casual games storefront at your WordPress.
Author: Zamango
Version: 1.6
Requires at least: 2.8
Author URI: http://www.zamango.com/
License: GPL
*/

require_once('zmg_admin.php');

/******************************************************************************/
if (!class_exists('zmg_money_extractor'))
{
    class zmg_money_extractor extends zmg_admin
    {
        var $hook        = 'zmg-money-extractor';
        var $version     = '1.6';
        var $page_title  = 'Money Extractor';
        var $menu_title  = 'Money Extractor';
        var $filename    = 'zamango-money-extractor/zmg_money_extractor.php';
        var $options     = array();
        var $no_promo    = true;

        /**********************************************************************/
        function zmg_money_extractor()
        {
            $this->chunk_size    = 20;
            $this->max_realgames = 6;
            $this->channels_root = 'http://account.zamango.com/channels/';
            $this->zmg_cached_categories = array();

            require_once('zmg_money_extractor_defaults.php');

            $this->dir_name    = basename(dirname(__FILE__));
            $this->plugin_url  = WP_PLUGIN_URL . '/' . $this->dir_name;
            $this->plugin_path = WP_PLUGIN_DIR . '/' . $this->dir_name;

            $this->reg_activation_hook();
            $this->reg_deactivation_hook();

            add_action('init', array($this, 'admin_init'), 1);
            add_action('init', array($this, 'init'));
        }

        /**********************************************************************/
        function activate()
        {
            global $wpdb;

            /*
             * Index on `guid` is very helpful. Don't know why
             * wordpress develeopers didn't add it yet
             */

            $query = "SHOW  INDEX
                      FROM `$wpdb->posts`
                     WHERE `Column_name`  = 'guid'
                       AND `Seq_in_index` = 1";

            $index = $wpdb->get_var($query);

            if (!$index)
                $wpdb->query("ALTER TABLE `$wpdb->posts` ADD INDEX (`guid`)");

            /*
             * Just noticed that some WP queries uses 'autoload'
             * field in `WHERE` clause
             */

            $query = "SHOW INDEX
                      FROM `$wpdb->options`
                     WHERE `Column_name`  = 'autoload'
                       AND `Seq_in_index` = 1";

            $index = $wpdb->get_var($query);

            if (!$index) $wpdb->query(
                        "ALTER TABLE `$wpdb->options` ADD INDEX (`autoload`)");

            /*
             * There is a little bit surprise: WP developers thinks
             * that two separate indexes on `post_id` and `meta_key` are
             * accelerating queries like:
             *
             *     SELECT `meta_value`
             *       FROM `wp_postmeta`
             *      WHERE `post_id`  = X AND
             *            `meta_key` = Y
             */

            /*
             * TODO:
             *
             *   ALTER TABLE `$wpdb->postmeta`
             *    DROP INDEX `post_id`
             *
             *   ALTER TABLE  `$wpdb->postmeta`
             *     ADD INDEX (`post_id`, `meta_key`)
             */
        }

        /**********************************************************************/
        function deactivate()
        {
            $this->options = get_option($this->hook);

            if ($this->options['clear_options'])
                delete_option($this->hook);

            wp_clear_scheduled_hook('zmg_auto_update');
        }

        /**********************************************************************/
        function init()
        {
            @set_time_limit(900);

            $this->add_js('zmg-me-admin-js', $this->plugin_url .
                          '/zmg_money_extractor_admin.js', true);

            $this->add_css('zmg-me-admin-css', $this->plugin_url .
                           '/zmg_money_extractor_admin.css', true);

            $this->add_css('zmg-me-css', $this->plugin_url .
                           '/zmg_money_extractor.css');

            $this->add_css('zmg-me-css', $this->plugin_url .
                           '/zmg_money_extractor.css', true);

            add_action('the_excerpt', array($this, 'zmg_process_permalink'));
            add_action('the_content', array($this, 'zmg_process_relgames'));
            add_action('the_excerpt_rss', array($this, 'zmg_process_permalink'));

            add_action('zmg_auto_update', array($this, 'zmg_auto_update'));

            add_filter('posts_join',    array($this, 'zmg_posts_join'));
            add_filter('posts_where',   array($this, 'zmg_posts_where'));
            add_filter('posts_groupby', array($this, 'zmg_posts_groupby'));
        }

        /**********************************************************************/
        function zmg_auto_update()
        {
            if (!defined('WP_IMPORTING'))
                define('WP_IMPORTING', true);

            /*
             * Dirty hack to turn off post revisions
             */

            if (!defined('DOING_AUTOSAVE'))
                define('DOING_AUTOSAVE', true);

            $this->zmg_auto_update_posts();
            $this->save_options();
        }

        /**********************************************************************/
        function zmg_auto_update_posts()
        {
            $url = $this->zmg_rss_url_newer();
            $rss = $this->zmg_get_rss($url, $message);

            if (!$rss)
                return;

            if (array_key_exists('item', $rss['rss']['channel']))
            {
                $post_status = $this->options['publish'] ? 'publish' : 'draft';

                /*
                 * Check if there is only one <item />
                 */

                if (array_key_exists('guid', $rss['rss']['channel']['item']))
                {
                    $items = array($rss['rss']['channel']['item']);
                }
                else
                {
                    $items = $rss['rss']['channel']['item'];
                }

                $count = count($items);

                for ($i = 0; $i < $count; $i++)
                {
                    $item = $items[$i];

                    $item['guid'] = 'http://' . $item['guid'];

                    $this->zmg_update_last_pubdate($item);

                    $post      = $this->zmg_get_post_data($item, $post_status);
                    $post_meta = $this->zmg_get_post_meta($item);

                    if ($post_meta['zmg_visible'])
                    {
                        $this->zmg_insert_post($post, $post_meta);
                    }
                    else if ($post['ID'])
                    {
                        $this->zmg_delete_post($post, $post_meta);
                    }
                }
            }
        }

        /**********************************************************************/
        function zmg_rss_url_newer()
        {
            $url  = $this->channels_root;
            $url .= rawurlencode($this->options['username']);
            $url .= '/';
            $url .= rawurlencode($this->options['channel']);
            $url .= '.rss?newer_than=';
            $url .= $this->options['last_pubdate'];

            return $url;
        }

        /**********************************************************************/
        function zmg_rss_url()
        {
            $chunk_size = isset($_POST['zmg_chunk_size'])
                          ?     $_POST['zmg_chunk_size']
                          : $this->chunk_size;

            $url  = $this->channels_root;
            $url .= rawurlencode($this->options['username']);
            $url .= '/';
            $url .= rawurlencode($this->options['channel']);
            $url .= '.rss?offset=';
            $url .= $this->options['processed'];
            $url .= '&limit=';
            $url .= isset($_POST['zmg_chunk_size'])
                  ? $_POST['zmg_chunk_size']
                  : $this->chunk_size;

            return $url;
        }

        /**********************************************************************/
        function zmg_update_last_pubdate(&$item)
        {
            if ($item['zmg:timestamp'] > $this->options['last_pubdate'])
                $this->options['last_pubdate'] = $item['zmg:timestamp'];
        }

        /**********************************************************************/
        function zmg_get_post_data(&$item, &$post_status)
        {
            $post = $this->zmg_get_post_by_guid($item['guid']);

            $post['guid']          = $item['guid'];
            $post['post_date_gmt'] = gmdate('Y-m-d H:i:s',
                                            $item['pubDate']
                                            ? strtotime($item['pubDate'])
                                            : time());
            $post['post_date']     = get_date_from_gmt($post['post_date_gmt']);
            $post['post_status']   = $post_status;
            $post['ping_status']   = 'closed';

            return $post;
        }

        /**********************************************************************/
        function zmg_get_post_by_guid(&$guid)
        {
            global $wpdb;

            return $wpdb->get_row("
                SELECT  *
                  FROM `$wpdb->posts`
                 WHERE `guid` = '$guid'
                 LIMIT  1
            ", ARRAY_A);
        }

        /**********************************************************************/
        function zmg_get_post_meta(&$item)
        {
            $post_meta = array();

            $post_meta['zmg_guid']        = trim($item['guid']);
            $post_meta['zmg_name']        = trim(  $item['title']
                                                 ? $item['title']
                                                 : $item['guid']);
            $post_meta['zmg_company']     = trim($item['zmg:company']);
            $post_meta['zmg_sysreq']      = $item['zmg:sysreq']
                                            ? trim($item['zmg:sysreq'])
                                            : '';
            $post_meta['zmg_image50']     = $item['zmg:image50'];
            $post_meta['zmg_image100']    = $item['zmg:image'];
            $post_meta['zmg_image140']    = $item['zmg:image140'];
            $post_meta['zmg_screenshot1'] = $item['zmg:screenshot1'];
            $post_meta['zmg_screenshot2'] = $item['zmg:screenshot2'];
            $post_meta['zmg_screenshot3'] = $item['zmg:screenshot3'];
            $post_meta['zmg_small_screenshot1'] = $item['zmg:small_screenshot1'];
            $post_meta['zmg_small_screenshot2'] = $item['zmg:small_screenshot2'];
            $post_meta['zmg_small_screenshot3'] = $item['zmg:small_screenshot3'];
            $post_meta['zmg_categories']  = $item['zmg:categories'];
            $post_meta['zmg_desc_80']     = $item['zmg:desc_80']
                                            ? trim($item['zmg:desc_80'])
                                            : '';
            $post_meta['zmg_desc_250']    = $item['description']
                                            ? trim($item['description'])
                                            : '';
            $post_meta['zmg_desc_2000']   = $item['zmg:desc_2000']
                                            ? trim($item['zmg:desc_2000'])
                                            : '';
            $post_meta['zmg_platform']    = $item['zmg:platform'];
            $post_meta['zmg_visible']     = $item['zmg:visible'];
            $post_meta['zmg_download']    = array();
            $post_meta['zmg_buy']         = array();
            $post_meta['zmg_relgames']    = is_array($item['zmg:relgames'])
                                            ? '' /* this is empty array */
                                            : $item['zmg:relgames'];

            if (array_key_exists('zmg:pc_link', $item))
            {
                $post_meta['zmg_download']['pc'] = $item['zmg:pc_link'];
            }

            if (array_key_exists('zmg:mac_link', $item))
            {
                $post_meta['zmg_download']['mac'] = $item['zmg:mac_link'];
            }

            if (array_key_exists('zmg:online_link', $item))
            {
                $post_meta['zmg_download']['online'] = $item['zmg:online_link'];
            }

            if (array_key_exists('zmg:pc_buy_link', $item))
            {
                $post_meta['zmg_buy']['pc'] = $item['zmg:pc_buy_link'];
            }

            if (array_key_exists('zmg:mac_buy_link', $item))
            {
                $post_meta['zmg_buy']['mac'] = $item['zmg:mac_buy_link'];
            }

            return $post_meta;
        }

        /***********************************************************************/
        function zmg_insert_post(&$post, &$post_meta)
        {
            if ($post['ID'] && get_post_meta($post['ID'], '_edit_lock', true))
            {
                /*
                 * Post edited manually, so we shouldn't update the content
                 */

                $this->zmg_update_post_meta($post['ID'], 'zmg_relgames',
                                            $post_meta['zmg_relgames']);

                return __('SKIPPED', $this->hook);
            }

            $post['tags_input']     = $this->zmg_get_post_tags($post_meta);
            $post['post_category']  = $this->zmg_get_post_categories($post_meta);

            $post['post_content']   = $this->zmg_process_template($post_meta,
                                                                  'template');
            $post['post_excerpt']   = $this->zmg_process_template($post_meta,
                                                                  'excerpt');
            if (!$post['ID'])
            {
                $post['post_title'] = $this->zmg_process_template($post_meta,
                                                                  'post_name');
            }

            $this->zmg_insert_post_custom($post);

            $post['ID'] = wp_insert_post($post);

            $this->zmg_update_post_meta($post['ID'], 'zmg_relgames',
                                        $post_meta['zmg_relgames']);
            return __('OK', $this->hook);
        }

        /**********************************************************************/
        function zmg_insert_post_custom(&$post)
        {
        }

        /**********************************************************************/
        function zmg_delete_post(&$post, &$post_meta)
        {
            if (get_post_meta($post['ID'], '_edit_lock', true))
            {
                /*
                 * Post edited manually, so we should mark it as draft
                 */

                $post['post_status'] = 'draft';

                wp_update_post($post);

                return __('DRAFTED', $this->hook);
            }
            else
            {
                wp_delete_post($post['ID']);

                return __('OK', $this->hook);
            }
        }

        /**********************************************************************/
        function zmg_get_post_tags(&$post_meta)
        {
            $post_tags = array();

            if ($this->options['tags_type'])
            {
                foreach (array_splice(explode('->',
                                      $post_meta['zmg_categories']),
                                      1, 1) as $type)
                {
                    array_push($post_tags, $type);
                }
            }

            if ($this->options['tags_genre'])
            {
                foreach (array_splice(explode('->',
                                      $post_meta['zmg_categories']),
                                      2) as $genre)
                {
                    array_push($post_tags, $genre);
                }
            }

            if ($this->options['tags_company'])
            {
                array_push($post_tags, $post_meta['zmg_company']);
            }

            return implode(',', $post_tags);
        }

        /**********************************************************************/
        function zmg_process_template(&$post_meta, $type = 'template')
        {
            $zmg_content = $this->options[$type];
            $zmg_content = preg_replace('/\[zmg:guid\]/',
                                        $post_meta['zmg_guid'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:description\]/',
                                        $post_meta['zmg_desc_2000'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:desc_250\]/',
                                        $post_meta['zmg_desc_250'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:desc_80\]/',
                                        $post_meta['zmg_desc_80'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:sysreq\]/',
                                        $post_meta['zmg_sysreq'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:image50\]/',
                                        $post_meta['zmg_image50'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:image100\]/',
                                        $post_meta['zmg_image100'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:image140\]/',
                                        $post_meta['zmg_image140'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:screenshot1\]/',
                                        $post_meta['zmg_screenshot1'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:screenshot2\]/',
                                        $post_meta['zmg_screenshot2'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:screenshot3\]/',
                                        $post_meta['zmg_screenshot3'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:small_screenshot1\]/',
                                        $post_meta['zmg_small_screenshot1'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:small_screenshot2\]/',
                                        $post_meta['zmg_small_screenshot2'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:small_screenshot3\]/',
                                        $post_meta['zmg_small_screenshot3'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:post_title\]/',
                                        $post_meta['zmg_name'],
                                        $zmg_content);

            if ($this->options['random_words'])
            {
                $zmg_content = preg_replace_callback('/\[zmg:random_word\]/',
                                                     array($this,
                                                     'zmg_replace_random_word'),
                                                     $zmg_content);
            }
            else
            {
                $zmg_content = preg_replace('/\[zmg:random_word\]/',
                                            '', $zmg_content);
            }

            $this->zmg_process_links($post_meta, $zmg_content);

            return $zmg_content;
        }

        /**********************************************************************/
        function zmg_replace_random_word($matches)
        {
            $words = $this->options['random_words'];

            shuffle($words);

            return array_shift($words);
        }

        /**********************************************************************/
        function zmg_process_relgames($content = '')
        {
            global $wpdb;

            /*
             * Do we have to process related games template?
             */

            $content = $this->zmg_process_permalink($content);

            if (!preg_match('/\[zmg:relgames\]/', $content))
                return $content;

            $zmg_relgames = '';

            $post_meta = get_post_custom();

            /*
             * Our post?
             */

            if ($post_meta['zmg_relgames'][0])
            {
                $limit = $this->options['max_relgames'];

                if ($limit <= 0)
                    $limit = $this->max_realgames;

                $guids = explode(',', $post_meta['zmg_relgames'][0]);
                $guids_count = count($guids);

                if ($limit < $guids_count)
                {
                    shuffle($guids);

                    $guids = array_splice($guids, 0, $limit);
                }
                else
                {
                    $limit = $guids_count;
                }

                foreach ($guids as &$guid)
                {
                    $guid = 'http://' . $guid;
                }

                $in = implode("','", $guids);

                $query = "
                    SELECT `ID`, `post_title`, `guid`
                      FROM `$wpdb->posts`
                     WHERE `guid` IN ('$in')
                     LIMIT  $limit
                ";

                $posts = $wpdb->get_results($query, ARRAY_A);

                $count = count($posts);

                if ($count)
                {
                    $zmg_relgames .= '<div class="zmg_relgames">';

                    for ($i = 0; $i < $count; $i++)
                    {
                        $post = $posts[$i];
                        $post['guid'] = preg_replace('/^http:\/\//', '',
                                                     $post['guid']);

                        $url = get_permalink($post['ID']);

                        $zmg_relgames .= '<div class="zmg_relgame_container">';
                        $zmg_relgames .= '<div class="zmg_relgame_item">';

                        $zmg_relgames .= '<a href="';
                        $zmg_relgames .= $url;
                        $zmg_relgames .= '" title="';
                        $zmg_relgames .= $post['post_title'];
                        $zmg_relgames .= '"><img ';
                        $zmg_relgames .= 'src="http://images.zamango.com/';
                        $zmg_relgames .= $this->options['language'];
                        $zmg_relgames .= '/';
                        $zmg_relgames .= $post['guid'];
                        $zmg_relgames .= '/50x50.gif" alt="';
                        $zmg_relgames .= $post['post_title'];
                        $zmg_relgames .= '" /></a><br />';
                        $zmg_relgames .= '<a href="';
                        $zmg_relgames .= $url;
                        $zmg_relgames .= '" title="';
                        $zmg_relgames .= $post['post_title'];
                        $zmg_relgames .= '">';
                        $zmg_relgames .= $post['post_title'];
                        $zmg_relgames .= '</a>';

                        $zmg_relgames .= '</div><div class="zmg_relgame_min">' .
                                         '</div></div>';
                    }

                    $zmg_relgames .= '</div>';
                }
            }

            if ($zmg_relgames)
            {
                $content = preg_replace('/\[zmg:relgames\]/',
                                        $zmg_relgames, $content);

                $content = preg_replace('/\[zmg:if_relgames\]/', '', $content);
                $content = preg_replace('/\[zmg:endif_relgames\]/', '',
                                        $content);
            }
            else
            {
                $content = preg_replace('/\[zmg:if_relgames\].*' .
                                        '?\[zmg:endif_relgames\]/sm', '',
                                        $content);
            }

            return $content;
        }

        /**********************************************************************/
        function zmg_process_permalink($content = '')
        {
            return preg_replace('/%zmg_post_permalink%/', get_permalink(),
                                $content);
        }

        /**********************************************************************/
        function zmg_process_links(&$post_meta, &$zmg_content)
        {
            $zmg_content = preg_replace('/\[zmg:button_bg\]/',
                                        $this->options['button_bg'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:button_src\]/',
                                        $this->options['button_src'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:buy_bg\]/',
                                        $this->options['buy_bg'],
                                        $zmg_content);

            $zmg_content = preg_replace('/\[zmg:buy_src\]/',
                                        $this->options['buy_src'],
                                        $zmg_content);

            if (array_key_exists('pc', $post_meta['zmg_download']))
            {
                $zmg_content = preg_replace('/\[zmg:download_pc_link\]/',
                                            $post_meta['zmg_download']['pc'],
                                            $zmg_content);

                $zmg_content = preg_replace('/\[zmg:if_download_pc\]/',
                                            '', $zmg_content);

                $zmg_content = preg_replace('/\[zmg:endif_download_pc\]/',
                                            '', $zmg_content);
            }
            else
            {
                $zmg_content = preg_replace('/\[zmg:if_download_pc\].*' .
                                            '?\[zmg:endif_download_pc\]/sm',
                                            '', $zmg_content);
            }

            if (array_key_exists('mac', $post_meta['zmg_download']))
            {
                $zmg_content = preg_replace('/\[zmg:download_mac_link\]/',
                                            $post_meta['zmg_download']['mac'],
                                            $zmg_content);

                $zmg_content = preg_replace('/\[zmg:if_download_mac\]/',
                                            '', $zmg_content);

                $zmg_content = preg_replace('/\[zmg:endif_download_mac\]/',
                                            '', $zmg_content);
            }
            else
            {
                $zmg_content = preg_replace('/\[zmg:if_download_mac\].*' .
                                            '?\[zmg:endif_download_mac\]/sm',
                                            '', $zmg_content);
            }

            if (array_key_exists('online', $post_meta['zmg_download']))
            {
                $zmg_content = preg_replace('/\[zmg:play_online_link\]/',
                                            $post_meta['zmg_download']['online'],
                                            $zmg_content);

                $zmg_content = preg_replace('/\[zmg:if_play_online\]/',
                                            '', $zmg_content);

                $zmg_content = preg_replace('/\[zmg:endif_play_online\]/',
                                            '', $zmg_content);
            }
            else
            {
                $zmg_content = preg_replace('/\[zmg:if_play_online\].*' .
                                            '?\[zmg:endif_play_online\]/sm',
                                            '', $zmg_content);
            }

            if (array_key_exists('pc', $post_meta['zmg_buy']))
            {
                $zmg_content = preg_replace('/\[zmg:buy_pc_link\]/',
                                            $post_meta['zmg_buy']['pc'],
                                            $zmg_content);

                $zmg_content = preg_replace('/\[zmg:if_buy_pc\]/',
                                            '', $zmg_content);

                $zmg_content = preg_replace('/\[zmg:endif_buy_pc\]/',
                                            '', $zmg_content);
            }
            else
            {
                $zmg_content = preg_replace('/\[zmg:if_buy_pc\].*' .
                                            '?\[zmg:endif_buy_pc\]/sm',
                                            '', $zmg_content);
            }

            if (array_key_exists('mac', $post_meta['zmg_buy']))
            {
                $zmg_content = preg_replace('/\[zmg:buy_mac_link\]/',
                                            $post_meta['zmg_buy']['mac'],
                                            $zmg_content);

                $zmg_content = preg_replace('/\[zmg:if_buy_mac\]/',
                                            '', $zmg_content);

                $zmg_content = preg_replace('/\[zmg:endif_buy_mac\]/',
                                            '', $zmg_content);
            }
            else
            {
                $zmg_content = preg_replace('/\[zmg:if_buy_mac\].*' .
                                            '?\[zmg:endif_buy_mac\]/sm',
                                            '', $zmg_content);
            }
        }

        /**********************************************************************/
        function zmg_get_post_categories(&$post_meta)
        {
            $categories = array();

            foreach (explode(',', $post_meta['zmg_categories']) as $category)
            {
                if (!array_key_exists($category, $this->zmg_cached_categories))
                {
                    $cat_id = $this->zmg_create_category($category);

                    $this->zmg_cached_categories[$category] = $cat_id;
                }

                array_push($categories, $this->zmg_cached_categories[$category]);
            }

            return $categories;
        }

        /**********************************************************************/
        function zmg_create_category(&$category)
        {
            $id = $this->options['root_category'];

            $names = explode('->', $category);

            if (!$this->options['hierarchical'])
            {
                $names = array_splice($names, 0, 1);
            }

            foreach ($names as $name)
            {
                $id = $this->zmg_get_category($name, $id);
            }

            return $id;
        }

        /**********************************************************************/
        function zmg_get_category(&$name, &$parent_id)
        {
            global $wpdb;

            $slug = sanitize_title($name . '-' . $parent_id);

            $wpdb->escape_by_ref($slug);

            $id = $wpdb->get_var("
                SELECT `tt`.`term_id`
                  FROM `$wpdb->term_taxonomy` AS `tt`
            INNER JOIN `$wpdb->terms` AS `t`
                    ON `t`.`term_id` = `tt`.`term_id`
                 WHERE `tt`.`taxonomy` = 'category'
                   AND `t`.`slug` = '$slug'
                   AND `tt`.`parent` = $parent_id
                 LIMIT  1
            ");

            if (!$id)
                $id = $this->zmg_insert_category($name, $slug, $parent_id);

            $this->zmg_update_excluded_cats($id);

            return $id;
        }

        /**********************************************************************/
        function zmg_insert_category($name, &$slug, &$parent_id)
        {
            global $wpdb;

            $wpdb->escape_by_ref($name);

            $wpdb->query("
                INSERT /* Very smart WordPress couldn't recognize
                          this query is `INSERT' without this comment */
                  INTO  `$wpdb->terms`
                       (`name`, `slug`)
                VALUES ('$name', '$slug')
            ");

            $id = $wpdb->insert_id;

            $wpdb->query("
                INSERT /* Very smart WordPress couldn't recognize
                          this query is `INSERT' without this comment */
                  INTO  `$wpdb->term_taxonomy`
                       (`term_id`, `taxonomy`, `parent`)
                VALUES ($id, 'category', $parent_id)
            ");

            return $id;
        }

        /**********************************************************************/
        function zmg_update_excluded_cats(&$id)
        {
            $excluded_cats = $this->options['excluded_cats']
                             ? explode(',', $this->options['excluded_cats'])
                             : array();

            array_push($excluded_cats, $id);
            $excluded_cats = array_unique($excluded_cats);

            $this->options['excluded_cats'] = implode(',', $excluded_cats);
        }

        /**********************************************************************/
        function zmg_update_post_meta($post_id, $key, $value)
        {
            global $wpdb;

            $meta_id = $wpdb->get_var("
                SELECT `meta_id`
                  FROM `$wpdb->postmeta`
                 WHERE `post_id`  = $post_id AND
                       `meta_key` = '$key'
                 LIMIT  1
            ");

            if ($meta_id)
            {
                $wpdb->query("
                    UPDATE `$wpdb->postmeta`
                       SET `meta_value` = '$value'
                     WHERE `meta_id` = $meta_id
                     LIMIT  1
                ");
            }
            else
            {
                $wpdb->query("
                    INSERT
                      INTO  `$wpdb->postmeta`
                           (`post_id`, `meta_key`, `meta_value`)
                    VALUES ($post_id, '$key', '$value')
                ");
            }
        }

        /**********************************************************************/
        function zmg_delete_posts($except_modified)
        {
            global $wpdb;

            $all_posts    = array();
            $except_posts = array();

            $query = "
                SELECT `post_id`
                  FROM `$wpdb->postmeta`
                 WHERE `meta_key` = 'zmg_relgames'
            ";

            $posts_id = $wpdb->get_results($query, ARRAY_N);

            foreach ($posts_id as $post_id)
            {
                array_push($all_posts, $post_id[0]);
            }

            if ($except_modified)
            {
                $posts_in = implode(',', $all_posts);

                $query = "
                    SELECT `post_id`
                      FROM `$wpdb->postmeta`
                     WHERE `post_id` IN ($posts_in) AND `meta_key` = '_edit_lock'
                ";

                $except_posts_id = $wpdb->get_results($query, ARRAY_N);

                foreach ($except_posts_id as $post_id)
                {
                    array_push($except_posts, $post_id[0]);
                }

                $all_posts = array_diff($all_posts, $except_posts);
            }

            foreach ($all_posts as $post_id)
            {
                wp_delete_post($post_id);
            }

            echo $this->disappearing_message(
                count($all_posts) . '/' . (count($all_posts) +
                count($except_posts)) . ' ' .
                __('posts deleted.', $this->hook)
            );
        }

        /**********************************************************************/
        function zmg_posts_join($sql)
        {
            global $wpdb;

            if ((is_home()  && $this->options['show_on_front'] == 0 &&
                               $this->options['excluded_cats']) ||
                (is_admin() && $this->options['show_on_admin'] == 0))
            {
                $sql .= " LEFT JOIN `$wpdb->term_relationships` AS `zmg_tr`
                                 ON `zmg_tr`.`object_id` = `$wpdb->posts`.`ID`

                          LEFT JOIN `$wpdb->term_taxonomy` AS `zmg_tt`
                                 ON `zmg_tt`.`taxonomy` = 'category' AND
                                    `zmg_tt`.`term_taxonomy_id` =
                                    `zmg_tr`.`term_taxonomy_id` ";
            }

            return $sql;
        }

        /**********************************************************************/
        function zmg_posts_where($sql)
        {
            if ((is_home()  && $this->options['show_on_front'] == 0 &&
                               $this->options['excluded_cats']) ||
                (is_admin() && $this->options['show_on_admin'] == 0))
            {
                $cats = $this->options['excluded_cats'];
                $sql .= " AND `zmg_tt`.`term_id` NOT IN ($cats) ";
            }

            return $sql;
        }

        /**********************************************************************/
        function zmg_posts_groupby($sql)
        {
            global $wpdb;

            if ((is_home()  && $this->options['show_on_front'] == 0 &&
                               $this->options['excluded_cats']) ||
                (is_admin() && $this->options['show_on_admin'] == 0))
            {
                return " `$wpdb->posts`.`ID` ";
            }
            else
            {
                return $sql;
            }
        }

        /**********************************************************************/
        function plugin_option_page_content()
        {
            if (isset($_POST['ZMG_UPDATE']))
            {
                $this->validate_params();

                if (isset($this->errors))
                {
                    echo $this->disappearing_message(
                        __('Incorrect settings value', $this->hook)
                    );

                    $this->zmg_show_options_page();
                }
                else
                {
                    $this->zmg_process_rss();
                    $this->save_options();
                }
            }
            elseif (isset($_POST['ZMG_OPTIONS']))
            {
                $this->validate_params();

                if (isset($this->errors))
                {
                    echo $this->disappearing_message(
                        __('Incorrect settings value', $this->hook)
                    );
                }
                else
                {
                    $this->save_options();
                    echo $this->disappearing_message(
                        __('Settings have been saved', $this->hook)
                    );
                }
                $this->zmg_show_options_page();
            }
            elseif (isset($_POST['ZMG_NEXT']))
            {
                $this->zmg_process_rss();
                $this->save_options();
            }
            elseif (isset($_POST['ZMG_DELETE']))
            {
                $this->zmg_delete_posts(isset($_POST['except_modified']));
                $this->save_options();
                $this->zmg_show_options_page();
            }
            else
            {
                $this->zmg_show_options_page();
            }
        }

        /**********************************************************************/
        function zmg_show_options_page()
        {
            $this->postbox($this->hook . '-import_update',
                           __('Import/Update', $this->hook),
                           $this->import_update());
            $this->postbox($this->hook . '-bulk_delete',
                           __('Bulk Delete', $this->hook),
                           $this->bulk_delete());
            $this->postbox($this->hook . '-realtime_options',
                           __('Realtime Options', $this->hook),
                           $this->realtime_options());
        }

        /**********************************************************************/
        function zmg_process_rss()
        {
            wp_clear_scheduled_hook('zmg_auto_update');

            wp_schedule_event(time() + 3600 * 12,
                              'twicedaily',
                              'zmg_auto_update');

            if (!defined('WP_IMPORTING'))
                define('WP_IMPORTING', true);

            /*
             * Dirty hack to turn off post revisions
             */

            if (!defined('DOING_AUTOSAVE'))
                define('DOING_AUTOSAVE', true);

            $start_time = microtime(1);
            $content    = '';

            $url = $this->zmg_rss_url();
            $rss = $this->zmg_get_rss($url, $message);

            if (!$rss)
            {
                echo $this->error_message(
                     __('Cannot fetch RSS. Please check if entered username ' .
                        'and channel name are correct. Note that Zamango ' .
                        'Money Extractor plugin requires outgoing connections' .
                        ' to be allowed from your server to proper retrieve ' .
                        'SS withb the games information.', $this->hook)
                );

                echo $this->error_message(__($message, $this->hook));

                $this->zmg_show_options_page();

                return;
            }

            $this->options['count'] = $rss['rss']['channel']['zmg:items_count'];
            $this->options['language'] = $rss['rss']['channel']['language'];

            $content .= $this->information_message('<strong>' .
                __('RSS Feed URL: ', $this->hook) . '</strong>' .
                htmlspecialchars(rawurldecode($url)));


            /*
             * Check whether rss is not empty
             */

            if (!array_key_exists('item', $rss['rss']['channel']))
            {
                $content .= '<p>' . $this->information_message(
                    __('Congratulations! Entire RSS is imported successfully',
                       $this->hook)
                ) . '</p><script type="text/javascript">' .
                'scroll(1, 10000000);</script>';

                $this->options['processed'] = 0;

                $this->postbox($this->hook . '-import_rss',
                               __('Import RSS', $this->hook),
                               $content);

                return;
            }

            /*
             * Check if there is only one <item />
             */

            if (is_object($rss['rss']['channel']['item']))
            {
                $items = array($rss['rss']['channel']['item']);
            }
            else
            {
                $items = $rss['rss']['channel']['item'];
            }

            $count = count($items);

            $chunk_size = isset($_POST['zmg_chunk_size'])
                          ?     $_POST['zmg_chunk_size']
                          : $this->chunk_size;

            $post_status = $this->options['publish'] ? 'publish' : 'draft';

            $rows = array();

            for ($i = 0; $i < $count; $i++)
            {
                $item = $items[$i];

                $item['guid'] = 'http://' . $item['guid'];

                $this->zmg_update_last_pubdate($item);

                $post      = $this->zmg_get_post_data($item, $post_status);
                $post_meta = $this->zmg_get_post_meta($item);

                $row   = array();
                $row[] = $this->elem(($this->options['processed'] + 1) . '/' .
                                      $this->options['count']);

                if ($post['ID'])
                {
                    if ($post_meta['zmg_visible'])
                    {
                        $row[] = $this->elem(__('Updating post...',
                                                $this->hook));
                        $row[] = $this->elem($this->zmg_insert_post($post,
                                                                   $post_meta));
                    }
                    else
                    {
                        $row[] = $this->elem(__('Deleting post...',
                                                $this->hook));
                        $row[] = $this->elem($this->zmg_delete_post($post,
                                                                   $post_meta));
                    }
                }
                else
                {
                    $row[] = $this->elem(__('Importing post...', $this->hook));
                    $row[] = $this->elem($post_meta['zmg_visible'] ?
                                         $this->zmg_insert_post($post,
                                                                $post_meta) :
                                         __('SKIPPED', $this->hook));
                }

                $row[] = $this->elem($post['post_title'] . '<script type="' .
                              'text/javascript">scroll(1, 10000000);</script>');
                $rows[] = $this->elem($row);

                $this->options['processed']++;
            }

            $content .= $this->table($rows, 'none', 'zmg_process_table', '');

            $this->options['execution_time'] = microtime(1) - $start_time;

            if ($i == $chunk_size && !($this->options['processed'] ==
                                       $this->options['count']))
            {
                $chunk = array(10, 20, 50, 100, 200, 500, 1000);

                $content .= '<form action="" method="post">';
                $content .= $this->hidden('ZMG_NEXT');
                $content .= '<p>';
                $content .= sprintf(
                    __('%d posts from total %d are inserted (updated) ' .
                       'in %.2f sec.', $this->hook),
                    $this->options['processed'],
                    $this->options['count'],
                    $this->options['execution_time']);
                $content .= '</p><p>';
                $content .= sprintf(
                    __('Please use button placed below few times until all %d' .
                       ' posts are updated.<br />Note that most hosts allow ' .
                       'each script to work up to 20&mdash;30 seconds, so ' .
                       'recommended number of posts for one iteration is 50, ' .
                       'but you may select greater or smaller one.',
                       $this->hook),
                    $this->options['count']);
                $content .= '</p><p>';
                $content .= __('Continue with', $this->hook) . ' ';
                $content .= $this->select('zmg_chunk_size', $chunk_size,
                                          $chunk);
                $content .= ' ' . __('posts per iteration', $this->hook) . ' ';
                $content .= $this->submit(__('Continue', $this->hook),
                                          'button-secondary');
                $content .= '</p><p>';
                $content .= __('If you receive time-out error please decrease' .
                               ' number of posts to update.', $this->hook);
                $content .= '</p>';
                $content .= '<script type="text/javascript">' .
                            'scroll(1, 10000000);</script>';
                $content .= '</form>';
            }
            else
            {
                $content .= '<p>' . $this->information_message(
                    __('Congratulations! Entire RSS is imported successfully',
                       $this->hook)
                ) . '</p><script type="text/javascript">' .
                'scroll(1, 10000000);</script>';

                $this->options['processed'] = 0;
            }

            $this->postbox($this->hook . '-import_rss',
                           __('Import RSS', $this->hook),
                           $content);
        }

        /**********************************************************************/
        function import_update()
        {
            $content  = '<p>';
            $content .= __('ZAMANGO tags', $this->hook);
            $content .= '</p>';

            $ul = array();

            $html  = '<code>[zmg:guid]</code> ';
            $html .= __('Game unique ID', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:image50]</code> ';
            $html .= __('Game icon src (50x50), used in related games',
                        $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:image100]</code> ';
            $html .= __('Game icon src (100x100)', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:image140]</code> ';
            $html .= __('Game icon src (140x140)', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:sysreq]</code> ';
            $html .= __('System requirements', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:post_title]</code> ';
            $html .= __('Game title', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:random_word]</code> ';
            $html .= __('Random word', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:small_screenshot1]</code>, ';
            $html .= '<code>[zmg:small_screenshot2]</code>, ';
            $html .= '<code>[zmg:small_screenshot3]</code> 1, 2, 3 ';
            $html .= __('Game small screenshot links', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:screenshot1]</code>, ';
            $html .= '<code>[zmg:screenshot2]</code>, ';
            $html .= '<code>[zmg:screenshot3]</code> 1, 2, 3 ';
            $html .= __('Game screenshot links', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:desc_80]</code> ';
            $html .= __('Game short description', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:desc_250]</code> ';
            $html .= __('Game medium description', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:description]</code> ';
            $html .= __('Game long description', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:if_relgames]/[zmg:endif_relgames]</code> ';
            $html .= __('Code inside will be evaluated if relgames exists',
                        $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:relgames]</code> ';
            $html .= __('Related games', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:if_download_pc]/' .
                     '[zmg:endif_download_pc]</code> ';
            $html .= __('Code inside will be evaluated if PC version exists',
                        $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:if_download_mac]/' .
                     '[zmg:endif_download_mac]</code> ';
            $html .= __('Code inside will be evaluated if Mac version exists',
                        $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:download_pc_link]</code> ';
            $html .= __('Link to download PC version', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:download_mac_link]</code> ';
            $html .= __('Link to download Mac version', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:if_buy_pc]/[zmg:endif_buy_pc]</code> ';
            $html .= __('Code inside will be evaluated if link to buy PC ' .
                        'version exists', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:if_buy_mac]/[zmg:endif_buy_mac]</code> ';
            $html .= __('Code inside will be evaluated if link to buy Mac ' .
                        'version exists', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:buy_pc_link]</code> ';
            $html .= __('Link to buy PC version', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:buy_mac_link]</code> ';
            $html .= __('Link to buy Mac version', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:if_play_online]/' .
                     '[zmg:endif_play_online]</code> ';
            $html .= __('Code inside will be evaluated if Online version ' .
                        'exists', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:play_online_link]</code> ';
            $html .= __('Link to play online', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>%zmg_post_permalink%</code> ';
            $html .= __('Permanent link to post', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:button_bg]</code> ';
            $html .= __('Download button color', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:button_src]</code> ';
            $html .= __('Link to download button image', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:buy_bg]</code> ';
            $html .= __('Buy button color', $this->hook);

            $ul[] = $this->elem($html);

            $html  = '<code>[zmg:buy_src]</code> ';
            $html .= __('Link to buy button image', $this->hook);

            $ul[] = $this->elem($html);

            $content .= $this->ul($ul);

            $content .= '<p>';
            $content .= __('Also you can use any HTML tags.', $this->hook);
            $content .= '</p>';

            $disc_link = $this->add_description(
                    __('Help on Zamango layout tags', $this->hook),
                    $content, 'rss_description');

            $content  = '<form action="" method="post">';
            $content .= $this->hidden('ZMG_UPDATE');
            $content .= $this->hidden('processed', '0');

            $error = $this->check_outgoing_connection();

            if ($error)
                $content .= $this->error_message(
                    __('Outgoing connections are disabled: ' . $error,
                     $this->hook)
                );

            $content .= $this->information_message(
                __('Import/update Zamango posts', $this->hook)
            );

            $rows = array();
            $row  = array();
            $html = __('Your username at Zamango.com', $this->hook);

            $row[] = $this->elem($html);

            $html  = $this->text('username', $this->options['username']);

            if ($this->errors['username'])
                $html .= $this->error_message(
                    __($this->errors['username'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html = __('Zamango channel name', $this->hook);

            $row[] = $this->elem($html);

            $html  = $this->text('channel', $this->options['channel']);

            if ($this->errors['channel'])
                $html .= $this->error_message(
                    __($this->errors['channel'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);

            $content .= $this->table($rows);

            $content .= '<script type="text/javascript">';
            $content .= 'window.zmg_hints = {';
            $content .= '"username" : "';
            $content .= __('enter username here', $this->hook);
            $content .= '", ';
            $content .= '"channel" : "';
            $content .= __('enter channel name here', $this->hook);
            $content .= '" };';
            $content .= '</script>';

            $content .= $this->information_message(
                __('Zamango M.E. plugin only converts RSS feed data into ' .
                   'Wordpress posts. So to use the plugin you must be ' .
                   '<a href="http://account.zamango.com/register.html" ' .
                   'target="_blank">registered at free Zamango service</a> ' .
                   'and create a channel with content for your blog. After ' .
                   'that paste your Zamango username and channel you wish ' .
                   'into corresponding fields above.', $this->hook)
            );

            $rows  = array();
            $row   = array();
            $html  = __('Maximum related games number in post', $this->hook);
            $row[] = $this->elem($html);
            $html  = $this->text('max_relgames',
                                 $this->options['max_relgames'], '',
                                 'zmg_short if_change');

            if ($this->errors['max_relgames'])
                $html .= $this->error_message(
                    __($this->errors['max_relgames'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html = __('Post entire feed in category', $this->hook);

            $row[] = $this->elem($html);

            $dropdown = wp_dropdown_categories(
                array('hide_empty'       => 0,
                      'name'             => 'root_category',
                      'selected'         => $this->options['root_category'],
                      'hierarchical'     => 1,
                      'show_option_none' => __('None', 'zmg_feed'),
                      'echo'             => 0
                )
            );

            if ($dropdown == '')
            {
                $html  = '<select name="root_category" class="post-form">';
                $html .= '  <option selected="selected" value="-1">';
                $html .= __('None', 'zmg_feed');
                $html .= '  </option>';
                $html .= '</select>';
            }
            else
            {
                $html = $dropdown;
            }

            if ($this->errors['root_category'])
                $html .= $this->error_message(
                    __($this->errors['root_category'], $this->hook));

            $html .= '<br />';
            $html .= $this->checkbox('hierarchical', '1',
                                     $this->options['hierarchical'],
                                     __('Create hierarchical category ' .
                                        'structure', $this->hook));

            if ($this->errors['hierarchical'])
                $html .= $this->error_message(
                    __($this->errors['hierarchical'], $this->hook));

            $html .= '<br />';
            $html .= $this->checkbox('show_on_front', '1',
                                     $this->options['show_on_front'],
                                     __('Show posts on the front page',
                                        $this->hook));

            if ($this->errors['show_on_front'])
                $html .= $this->error_message(
                    __($this->errors['show_on_front'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html = __('Publish new posts', $this->hook);

            $row[] = $this->elem($html);

            $label = __('It is recommended to keep this box checked. ' .
                        'If you uncheck it, all syndicated posts will ' .
                        'be created as <b>drafts</b> and therefore they will ' .
                        'be invisible for visitors of your site.',
                        $this->hook);

            $html = $this->checkbox('publish', '1',
                                     $this->options['publish'], $label);

            if ($this->errors['publish'])
                $html .= $this->error_message(
                    __($this->errors['publish'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html = __('Create tags for', $this->hook);

            $row[] = $this->elem($html);

            $html  = $this->checkbox('tags_type', '1',
                                     $this->options['tags_type'],
                                     __('Types (i.e. Windows or MacOS)',
                                        $this->hook));

            if ($this->errors['tags_type'])
                $html .= $this->error_message(
                    __($this->errors['tags_type'], $this->hook));

            $html .= '<br />';
            $html .= $this->checkbox('tags_genre', '1',
                                     $this->options['tags_genre'],
                                     __('Genres (i.e. Puzzle or Mahjong)',
                                        $this->hook));

            if ($this->errors['tags_genre'])
                $html .= $this->error_message(
                    __($this->errors['tags_genre'], $this->hook));

            $html .= '<br />';
            $html .= $this->checkbox('tags_company', '1',
                                     $this->options['tags_company'],
                                     __('Publishers (i.e. BigFishGames or ' .
                                        'KatGames)', $this->hook));

            if ($this->errors['tags_company'])
                $html .= $this->error_message(
                    __($this->errors['tags_company'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Post name layout', $this->hook);
            $html .= '<br /><small>';
            $html .= '<a href="#help_for_zmg_tags"';
            $html .= 'onClick="' . $disc_link . '"';
            $html .= 'return false;" title="';
            $html .= __('Help on ZMG tags', $this->hook) . '">';
            $html .= __('Help...', $this->hook);
            $html .= '</a></small>';

            $row[]  = $this->elem($html);

            $html = $this->text('post_name', $this->options['post_name'],
                                __('Usually it used for SEO optimization, ' .
                                   'e.g if template looks like <i>"' .
                                   '[zmg:random_word] [zmg:post_title]"</i> ' .
                                   'then post title will be <i>"Download ' .
                                   'game Zuma"</i>', $this->hook),
                                   'if_change zmg_long_text_input');

            if ($this->errors['post_name'])
                $html .= $this->error_message(
                    __($this->errors['post_name'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Random words', $this->hook);

            $row[] = $this->elem($html);

            $html  = $this->textarea('random_words', implode(',',
                                     $this->options['random_words']), 2, 80,
                                     __('Enter here comma separated random ' .
                                        'words if you would like to get them ' .
                                        'in post title, e.g: <i>Free ' .
                                        'download, Download game, Free ' .
                                        'game</i>', $this->hook), 'if_change');

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Excerpt layout', $this->hook);
            $html .= '<br /><small>';
            $html .= '<a href="#help_for_zmg_tags"';
            $html .= 'onClick="' . $disc_link . '"';
            $html .= 'return false;" title="';
            $html .= __('Help on ZMG tags', $this->hook) . '">';
            $html .= __('Help...', $this->hook);
            $html .= '</a></small>';

            $row[] = $this->elem($html);

            $html  = '<div>';
            $html .= '<a href="#template" class="zmg_active" onclick="zmg_show_template(\'excerpt\'); return false;">';
            $html .= __('Template', $this->hook);
            $html .= '</a>';
            $html .= '<a href="#preview" onclick="zmg_show_preview(\'excerpt\'); return false;">';
            $html .= __('Preview', $this->hook);
            $html .= '</a>';
            $html .= '</div>';

            $html .= $this->textarea('excerpt', $this->options['excerpt'],
                                     13, 80);

            $html .= '<div id="zmg_preview_excerpt"></div>';

            $row[]  = $this->elem($html, 'zmg_excerpt');
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Content layout', $this->hook);
            $html .= '<br /><small>';
            $html .= '<a href="#help_for_zmg_tags"';
            $html .= 'onClick="' . $disc_link . '"';
            $html .= 'return false;" title="';
            $html .= __('Help on ZMG tags', $this->hook) . '">';
            $html .= __('Help...', $this->hook);
            $html .= '</a></small>';

            $row[] = $this->elem($html);

            $html  = '<div>';
            $html .= '<a href="#template" class="zmg_active" onclick="zmg_show_template(\'template\'); return false;">';
            $html .= __('Template', $this->hook);
            $html .= '</a>';
            $html .= '<a href="#preview" onclick="zmg_show_preview(\'template\'); return false;">';
            $html .= __('Preview', $this->hook);
            $html .= '</a>';
            $html .= '</div>';

            $html .= $this->textarea('template', $this->options['template'],
                                     49, 80);

            $html .= '<div id="zmg_preview_template"></div>';

            $row[]  = $this->elem($html, 'zmg_template');
            $rows[] = $this->elem($row);
            $row    = array();
            $row[]  = $this->elem('<hr>');
            $row[]  = $this->elem('<hr>');
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Download button type', $this->hook);

            $row[]  = $this->elem($html);

            $ul = array();

            foreach (array(24, 32, 48, 64) as $size)
            {
                $label = '<img class="zmg_img_download" src="' .
                         $this->plugin_url . '/img/' . $size . 'x' . $size .
                         '.png" />';

                $html  = $this->radio('button_size', $size,
                                      $this->options['button_size'] == $size,
                                      $label, 'if_change');

                $ul[]  = $this->elem($html);
            }

            $label  = '<img class="zmg_img_download" src="';
            $label .= ($this->options['button_size'] == 0) ?
                      $this->options['button_src'] :
                      $this->plugin_url . '/img/0x0.png';
            $label .= '" />';

            $html  = $this->radio('button_size', 0,
                                  $this->options['button_size'] == 0, $label,
                                  'if_change');

            if ($this->errors['button_size'])
                $html .= $this->error_message(
                    __($this->errors['button_size'], $this->hook));

            $ul[]  = $this->elem($html);

            $html  = $this->ul($ul, 'row', 'zmg_button_buttons');
            $html .= $this->text('button_src',
                                 ($this->options['button_src']) ?
                                 $this->options['button_src'] :
                                 $this->plugin_url . '/img/' .
                                 $this->options['button_size'] . 'x' .
                                 $this->options['button_size'] . '.png', '',
                                 'if_change zmg_long_text_input');

            if ($this->errors['button_src'])
                $html .= $this->error_message(
                    __($this->errors['button_src'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Download button color', $this->hook);

            $row[]  = $this->elem($html);

            $html  = $this->text('button_bg', $this->options['button_bg'], '',
                                 'zmg_short if_change');
            $html .= '<div id="zmg_button_palette" class="zmg_clear">';
            $html .= '<img onclick="zmg_palette_change_bg(\'transparent\', \'button\')"';
            $html .= 'src="' . $this->plugin_url;
            $html .= '/img/transparent_template.gif" /></div>';

            if ($this->errors['button_bg'])
                $html .= $this->error_message(
                    __($this->errors['button_bg'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();
            $row[]  = $this->elem('<hr>');
            $row[]  = $this->elem('<hr>');
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Buy button type', $this->hook);

            $row[]  = $this->elem($html);

            $ul = array();

            foreach (array(24, 32, 48, 64) as $size)
            {
                $label = '<img class="zmg_img_download" src="' .
                         $this->plugin_url . '/img/buy_' . $size . 'x' . $size .
                         '.png" />';

                $html  = $this->radio('buy_size', $size,
                                      $this->options['buy_size'] == $size,
                                      $label, 'if_change');

                $ul[]  = $this->elem($html);
            }

            $label  = '<img class="zmg_img_download" src="';
            $label .= ($this->options['buy_size'] == 0) ?
                      $this->options['buy_src'] :
                      $this->plugin_url . '/img/0x0.png';
            $label .= '" />';

            $html  = $this->radio('buy_size', 0,
                                  $this->options['buy_size'] == 0, $label,
                                  'if_change');

            if ($this->errors['buy_size'])
                $html .= $this->error_message(
                    __($this->errors['buy_size'], $this->hook));

            $ul[]  = $this->elem($html);

            $html  = $this->ul($ul, 'row', 'zmg_buy_buttons');
            $html .= $this->text('buy_src',
                                 ($this->options['buy_src']) ?
                                 $this->options['buy_src'] :
                                 $this->plugin_url . '/img/buy_' .
                                 $this->options['buy_size'] . 'x' .
                                 $this->options['buy_size'] . '.png', '',
                                 'if_change zmg_long_text_input');

            if ($this->errors['buy_src'])
                $html .= $this->error_message(
                    __($this->errors['buy_src'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Buy button color', $this->hook);

            $row[]  = $this->elem($html);

            $html  = $this->text('buy_bg', $this->options['buy_bg'], '',
                                 'zmg_short if_change');
            $html .= '<div id="zmg_buy_palette" class="zmg_clear"><img';
            $html .= ' onclick="zmg_palette_change_buy_bg(\'transparent\', \'buy\')"';
            $html .= 'src="' . $this->plugin_url;
            $html .= '/img/transparent_template.gif" /></div>';

            if ($this->errors['buy_bg'])
                $html .= $this->error_message(
                    __($this->errors['buy_bg'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();
            $row[]  = $this->elem('');
            $html   = $this->submit(__('Syndicate Now', $this->hook));
            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);

            $content .= $this->table($rows);

            $content .= '</form>';

            return $content;
        }

        /**********************************************************************/
        function bulk_delete()
        {
            $content  = '<form action="" method="post">';
            $content .= $this->hidden('ZMG_DELETE');

            $rows = array();
            $row  = array();
            $html = __('Delete all Zamango posts', $this->hook);

            $row[] = $this->elem($html);

            $html  = $this->checkbox('except_modified', '1',
                                     $this->options['except_modified'],
                                     __('Except posts modified by me',
                                        $this->hook));

            if ($this->errors['except_modified'])
                $html .= $this->error_message(
                    __($this->errors['except_modified'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();
            $row[]  = $this->elem('');
            $html   = $this->submit(__('Delete Now', $this->hook));
            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);

            $content .= $this->table($rows);

            $content .= '</form>';

            return $content;
        }

        /**********************************************************************/
        function realtime_options()
        {
            $content  = '<form action="" method="post">';
            $content .= $this->hidden('ZMG_OPTIONS');

            $content .= $this->information_message(
                __('These options takes effect <strong>without</strong> ' .
                   'posts import/update', $this->hook)
            );

            $rows = array();
            $row  = array();

            $html  = __('Maximum related games number in post', $this->hook);
            $row[] = $this->elem($html);

            $html  = $this->text('max_relgames',
                                 $this->options['max_relgames'], '',
                                 'zmg_short');

            if ($this->errors['max_relgames'])
                $html .= $this->error_message(
                    __($this->errors['max_relgames'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $row[] = $this->elem('');

            $html  = $this->checkbox('show_on_front', '1',
                                     $this->options['show_on_front'],
                                     __('Show posts on the front page',
                                        $this->hook));

            if ($this->errors['show_on_front'])
                $html .= $this->error_message(
                    __($this->errors['show_on_front'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $row[] = $this->elem('');

            $html  = $this->checkbox('show_on_admin', '1',
                                     $this->options['show_on_admin'],
                                     __('Show posts on the admin page (Edit ' .
                                        'Posts)', $this->hook));

            if ($this->errors['show_on_admin'])
                $html .= $this->error_message(
                    __($this->errors['show_on_admin'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $row[] = $this->elem('');

            $html  = $this->checkbox('clear_options', '1',
                                     $this->options['clear_options'],
                                     __('Clear options on plugin deactivation',
                                        $this->hook));

            if ($this->errors['clear_options'])
                $html .= $this->error_message(
                    __($this->errors['clear_options'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Post name layout', $this->hook);

            $row[]  = $this->elem($html);

            $html = $this->text('post_name', $this->options['post_name'],
                                __('Usually it used for SEO optimization, ' .
                                   'e.g if template looks like <i>"' .
                                   '[zmg:random_word] [zmg:post_title]"</i> ' .
                                   'then post title will be <i>"Download ' .
                                   'game Zuma"</i>', $this->hook),
                                   'if_change zmg_long_text_input');

            if ($this->errors['post_name'])
                $html .= $this->error_message(
                    __($this->errors['post_name'], $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();

            $html  = __('Random words', $this->hook);

            $row[] = $this->elem($html);

            $html  = $this->textarea('random_words', implode(',',
                                     $this->options['random_words']), 2, 80,
                                     __('Enter here comma separated random ' .
                                        'words if you would like to get them ' .
                                        'in post title, e.g: <i>Free ' .
                                        'download, Download game, Free game</i>',
                                        $this->hook));

            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);
            $row    = array();
            $row[]  = $this->elem('');
            $html   = $this->submit(__('Save', $this->hook));
            $row[]  = $this->elem($html);
            $rows[] = $this->elem($row);

            $content .= $this->table($rows);

            $content .= '</form>';

            return $content;
        }

        /**********************************************************************/
        function check_outgoing_connection()
        {
            $url = 'http://www.google.com';
            $opts = array(
                'method'     => 'GET',
                'timeout'    => 60,
                'user-agent' => 'Mozilla/5.0 (compatible; Zamango M.E./' .
                                 $this->version .
                                '; +http://www.zamango.com/about-zamango.html)',
                'headers'    => array('Referer' => get_option('siteurl'))
            );

            $message = '';
            $response = wp_remote_request($url, $opts);

            if (is_object($response) && $response->errors)
            {
                if (array_key_exists('http_request_failed', $response->errors))
                {
                    $message = $response->errors['http_request_failed'][0];
                }
                else
                {
                    $message = 'Unknown error';
                }
            }

            return $message;
        }

        /**********************************************************************/
        function zmg_get_rss($url, &$message)
        {
            if (!$url)
                return array();

            $opts = array(
                'method'     => 'GET',
                'timeout'    => 60,
                'user-agent' => 'Mozilla/5.0 (compatible; Zamango M.E./' .
                                 $this->version .
                                '; +http://www.zamango.com/about-zamango.html)',
                'headers'    => array('Referer' => get_option('siteurl'))
            );

            $response = wp_remote_request($url, $opts);

            if (is_object($response) && $response->errors)
            {
                if (array_key_exists('http_request_failed', $response->errors))
                {
                    $message = $response->errors['http_request_failed'][0];
                }
                else
                {
                    $message = 'Unknown error';
                }

                return array();
            }

            if ($response['response']['code'] != 200)
            {
                $message = $response['response']['code'] . ': ' .
                           $response['response']['message'];

                return array();
            }

            $body_len = $response['headers']['content-length'];
            $real_len = strlen($response['body']);

            if ($body_len != $real_len)
            {
                $message = "Fetched only $real_len of $body_len bytes";
                return array();
            }

            /*
             * Get the XML parser of PHP.
             * PHP must have this module for the parser to work
             */

            $p = xml_parser_create();

            xml_parser_set_option($p, XML_OPTION_CASE_FOLDING, 0);
            xml_parser_set_option($p, XML_OPTION_SKIP_WHITE,   1);
            xml_parse_into_struct($p, $response['body'], $values);
            xml_parser_free      ($p);

            if (!$values)
            {
                $message = "Can't parse RSS";
                return array();
            }

            /*
             * Initializations
             */

            $xml_array   = array();
            $parents     = array();
            $opened_tags = array();
            $arr         = array();

            /*
             * Reference
             */

            $current = &$xml_array;

            /*
             * Multiple tags with same name will be turned into an array
             */

            $repeated_tag_index = array();

            /*
             * Go through the tags.
             */

            foreach ($values as $data)
            {
                /*
                 * Remove existing values, or there will be trouble
                 */

                unset($value);

                /*
                 * This command will extract these variables into the foreach scope
                 * tag(string), type(string), level(int), attributes(array).
                 * We could use the array by itself, but this cooler.
                 */

                extract($data);

                $result = array();

                if (isset($value))
                    $result = $value;

                /*
                 * See tag status and do the needed.
                 */

                if ($type == 'open')
                {
                    /*
                     * The starting of the tag '<tag>'
                     */

                    $parent[$level - 1] = &$current;

                    if (!is_array($current) ||
                        !in_array($tag, array_keys($current)))
                    {
                        /*
                         * Insert New tag
                         */

                        $current[$tag] = $result;
                        $repeated_tag_index[$tag.'_'.$level] = 1;
                        $current = &$current[$tag];
                    }
                    else
                    {
                        /*
                         * There was another element with the same tag name
                         */

                        if (isset($current[$tag][0]))
                        {
                            /*
                             * If there is a 0th element it is already an array
                             */

                            $current[$tag][$repeated_tag_index[$tag.'_'.$level]]
                                = $result;
                            $repeated_tag_index[$tag.'_'.$level]++;
                        }
                        else
                        {
                            /*
                             * This section will make the value an array if
                             * multiple tags with the same name appear together.
                             */

                            /*
                             * This will combine the existing item and the new item
                             * together to make an array
                             */

                            $current[$tag] = array($current[$tag],$result);
                            $repeated_tag_index[$tag.'_'.$level] = 2;
                        }

                        $last_item_index =
                            $repeated_tag_index[$tag.'_'.$level] - 1;
                        $current = &$current[$tag][$last_item_index];
                    }
                }
                elseif ($type == 'complete')
                {
                    /*
                     * Tags that ends in 1 line '<tag />'.
                     * See if the key is already taken.
                     */

                    if (!isset($current[$tag]))
                    {
                        /*
                         * New Key
                         */

                        $current[$tag] = $result;
                        $repeated_tag_index[$tag.'_'.$level] = 1;
                    }
                    else
                    {
                        /*
                         * If taken, put all things inside a list(array)
                         */

                        if (isset($current[$tag][0]) and
                            is_array($current[$tag]))
                        {
                            /*
                             * If it is already an array...
                             * ...push the new element into that array.
                             */

                            $current[$tag][$repeated_tag_index[$tag.'_'.$level]]
                                = $result;
                            $repeated_tag_index[$tag.'_'.$level]++;
                        }
                        else
                        {
                            /*
                             * If it is not an array...
                             * ...Make it an array using the existing
                             * value and the new value
                             */

                            $current[$tag] = array($current[$tag], $result);

                            /*
                             * 0 and 1 index is already taken
                             */

                            $repeated_tag_index[$tag.'_'.$level] = 2;
                        }
                    }
                }
                elseif ($type == 'close')
                {
                    /*
                     * End of tag '</tag>'
                     */

                    $current = &$parent[$level - 1];
                }
            }

            return $xml_array;
        }
    }

    $zmg_money_extractor = new zmg_money_extractor();
}

