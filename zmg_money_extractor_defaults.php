<?
    load_plugin_textdomain($this->hook,
                           $this->plugin_url,
                           $this->dir_name);

    $this->default_options =  array(
        "username"         => array(
            "default"      => __('enter username here', $this->hook),
            "minlen"       => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "channel"          => array(
            "default"      => __('enter channel name here', $this->hook),
            "minlen"       => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "language"         => array(
            "default"      => 'en'
        ),
        "root_category"    => array(
            "default"      => 0,
            "regs"         => array('/^-?\d+$/'),
            "callback"     => create_function ('&$obj',
                'if ($obj->options[\'root_category\'] == -1)
                    $obj->options[\'root_category\'] = 0;'),
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "hierarchical"     => array(
            "default"      => 1,
            "definedornot" => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "publish"          => array(
            "default"      => 1,
            "definedornot" => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "tags_type"        => array(
            "default"      => 1,
            "definedornot" => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "tags_genre"       => array(
            "default"      => 1,
            "definedornot" => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "tags_company"     => array(
            "default"      => 1,
            "definedornot" => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "last_pubdate"     => array(
            "default"      => 0
        ),
        "max_relgames"     => array(
            "default"      => $this->max_realgames,
            "regs"         => array('/^\d+$/')
        ),
        "button_size"      => array(
            "default"      => 32,
            "regs"         => array('/^(0|24|32|48|64)$/'),
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "button_bg"        => array(
            "default"      => 'transparent',
            "regs"         => array('/^(transparent|#[0-9A-Fa-f]{6})$/'),
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "button_src"       => array(
            "default"      => '',
            "required"     => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "buy_size"         => array(
            "default"      => 32,
            "regs"         => array('/^(0|24|32|48|64)$/'),
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "buy_bg"           => array(
            "default"      => 'transparent',
            "regs"         => array('/^(transparent|#[0-9A-Fa-f]{6})$/'),
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "buy_src"          => array(
            "default"      => '',
            "required"     => 1,
            "stoper"       => create_function('',
                'return ! isset($_POST[\'ZMG_UPDATE\']);')
        ),
        "show_on_admin"    => array(
            "default"      => 1,
            "definedornot" => 1
        ),
        "show_on_front"    => array(
            "default"      => 1,
            "definedornot" => 1
        ),
        "excluded_cats"    => array(
            "default"      => ''
        ),
        "count"            => array(
            "default"      => 0
        ),
        "processed"        => array(
            "default"      => 0
        ),
        "clear_options"    => array(
            "default"      => 0,
            "definedornot" => 1
        ),
        "post_name"        => array(
            "default"      => '[zmg:post_title]',
            "minlen"       => 1
        ),
        "random_words"     => array(
            "default"      => array(),
            "callback"     => create_function ('&$obj',
                '$obj->options[\'random_words\'] =' .
                    'explode(\',\', stripslashes($_POST[\'random_words\']));')
        ),
        "excerpt"          => array(
            "default"      => '
<a href="%zmg_post_permalink%" title="[zmg:post_title]"><img class="zmg_img_100" src="[zmg:image100]" alt="[zmg:post_title]" /></a>[zmg:desc_250]

<div class="zmg_clear">
  <a target="_blank" href="[zmg:screenshot1]" title="[zmg:post_title]" class="thickbox" rel="lightbox [zmg:guid]"><img class="zmg_img_small" src="[zmg:small_screenshot1]" alt="[zmg:post_title]" /></a> <a target="_blank" href="[zmg:screenshot2]" title="[zmg:post_title]" class="thickbox" rel="lightbox [zmg:guid]"><img class="zmg_img_small" src="[zmg:small_screenshot2]" alt="[zmg:post_title]" /></a> <a target="_blank" href="[zmg:screenshot3]" title="[zmg:post_title]" class="thickbox" rel="lightbox [zmg:guid]"><img class="zmg_img_small" src="[zmg:small_screenshot3]" alt="[zmg:post_title]" /></a>
</div>
            ',
        ),
        "template"         => array(
            "default"      => '
<img class="zmg_img_140" src="[zmg:image140]" alt="[zmg:post_title]" />[zmg:description]
<div class="zmg_clear"></div>

<!--more-->

<div class="zmg_clear"><small>[zmg:sysreq]</small></div>

[zmg:if_download_pc]
<div class="zmg_download">
  <a title="'. __('Download PC version', $this->hook) .'" href="[zmg:download_pc_link]"><img class="zmg_img_download" style="background-color:[zmg:button_bg]" src="[zmg:button_src]" alt="'. __('Download PC version', $this->hook) .'" /></a> <a class="zmg_download" title="'. __('Download PC version', $this->hook) .'" href="[zmg:download_pc_link]">'. __('Download PC version', $this->hook) .'</a>
</div>
[zmg:endif_download_pc]

[zmg:if_buy_pc]
<div class="zmg_download">
  <a title="'. __('Buy PC version', $this->hook) .'" href="[zmg:buy_pc_link]"><img class="zmg_img_download" style="background-color:[zmg:buy_bg]" src="[zmg:buy_src]" alt="'. __('Buy PC version', $this->hook) .'" /></a><a class="zmg_download" title="'. __('Buy PC version', $this->hook) .'" href="[zmg:buy_pc_link]">'. __('Buy PC version', $this->hook) .'</a>
</div>
[zmg:endif_buy_pc]

[zmg:if_download_mac]
<div class="zmg_download">
  <a title="'. __('Download MacOS version', $this->hook) .'" href="[zmg:download_mac_link]"><img class="zmg_img_download" style="background-color:[zmg:button_bg]" src="[zmg:button_src]" alt="'. __('Download MacOS version', $this->hook) .'" /></a><a title="'. __('Download MacOS version', $this->hook) .'" href="[zmg:download_mac_link]">'. __('Download MacOS version', $this->hook) .'</a>
</div>
[zmg:endif_download_mac]

[zmg:if_buy_mac]
<div class="zmg_download">
  <a title="'. __('Buy MacOS version', $this->hook) .'" href="[zmg:buy_mac_link]"><img class="zmg_img_download" style="background-color:[zmg:buy_bg]" src="[zmg:buy_src]" alt="'. __('Buy MacOS version', $this->hook) .'" /></a><a title="'. __('Buy MacOS version', $this->hook) .'" href="[zmg:buy_mac_link]">'. __('Buy MacOS version', $this->hook) .'</a>
</div>
[zmg:endif_buy_mac]

[zmg:if_play_online]
<div class="zmg_download">
  <a title="'. __('Play online', $this->hook) .'" href="[zmg:play_online_link]"><img class="zmg_img_download" style="background-color:[zmg:button_bg]" src="[zmg:button_src]" alt="'. __('Play online', $this->hook) .'" /></a><a title="'. __('Play online', $this->hook) .'" href="[zmg:play_online_link]">'. __('Play online', $this->hook) .'</a>
</div>
[zmg:endif_play_online]

<div class="zmg_clear">
  <a target="_blank" href="[zmg:screenshot1]" title="[zmg:post_title]" class="thickbox" rel="lightbox [zmg:guid]"><img class="zmg_img_small" src="[zmg:small_screenshot1]" alt="[zmg:post_title]" /></a><a target="_blank" href="[zmg:screenshot2]" title="[zmg:post_title]" class="thickbox" rel="lightbox [zmg:guid]"><img class="zmg_img_small" src="[zmg:small_screenshot2]" alt="[zmg:post_title]" /></a><a target="_blank" href="[zmg:screenshot3]" title="[zmg:post_title]" class="thickbox" rel="lightbox [zmg:guid]"><img class="zmg_img_small" src="[zmg:small_screenshot3]" alt="[zmg:post_title]" /></a>
</div>

[zmg:if_relgames]
  <p>'. __('Related games:', $this->hook) .'</p>
  [zmg:relgames]
[zmg:endif_relgames]
            '
        )
    );

