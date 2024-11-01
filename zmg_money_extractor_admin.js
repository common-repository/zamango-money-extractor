/******************************************************************************/
jQuery(document).ready(function(){
    jQuery('input:radio').bind('change', function () {
        var src   = jQuery(this).parent().find('img').attr('src');
        var name  = jQuery(this).attr('name');
        var val   = jQuery(this).val();
        var srcid = name.replace('size', 'src');

        if (val == 0)
        {
            src = 'http://Enter your full URL here.png';
        }

        jQuery('#zmg-money-extractor-' + srcid).val(src);
    });

    jQuery('.if_change').change(function () {
        zmg_render_preview('template');
        zmg_render_preview('excerpt');
    });

    zmg_palette_display('button');
    zmg_palette_display('buy');

    zmg_palette_change_bg(jQuery('#zmg-money-extractor-button_bg').val(), 'button')
    zmg_palette_change_bg(jQuery('#zmg-money-extractor-buy_bg').val(), 'buy');

    jQuery('#zmg-money-extractor-button_bg').change(function () {
        zmg_palette_change_bg(jQuery(this).val(), 'button');
    });

    jQuery('#zmg-money-extractor-buy_bg').change(function () {
        zmg_palette_change_bg(jQuery(this).val(), 'buy');
    });

    jQuery('#zmg-money-extractor-username').blur(function () {
        if (jQuery(this).val() == '')
            jQuery(this).val(zmg_hints['username']);
    }).focus(function () {
        if (jQuery(this).val() == zmg_hints['username'])
            jQuery(this).val('');
    });

    jQuery('#zmg-money-extractor-channel').blur(function () {
        if (jQuery(this).val() == '')
            jQuery(this).val(zmg_hints['channel']);
    }).focus(function () {
        if (jQuery(this).val() == zmg_hints['channel'])
            jQuery(this).val('');
    });

    jQuery("#zmg-money-extractor-realtime_options input").each(function () {
        var id = jQuery(this).attr('id');

        if (!id || id == 'zmg-money-extractor-ZMG_OPTIONS') return;

        jQuery(this).attr('id', id + '2');
    });

    jQuery("#zmg-money-extractor-realtime_options textarea").each(function () {
        var id = jQuery(this).attr('id');

        jQuery(this).attr('id', id + '2');
    });

    jQuery("#zmg-money-extractor-realtime_options label").each(function () {
        var id = jQuery(this).attr('for');

        jQuery(this).attr('for', id + '2');
    });
});

/******************************************************************************/
function zmg_show_template(type)
{
    jQuery('#zmg_' + type).find('a:eq(0)').addClass('zmg_active');
    jQuery('#zmg_' + type).find('a:eq(1)').removeClass('zmg_active');

    jQuery('#zmg_preview_' + type).hide();
    jQuery('#zmg-money-extractor-' + type).show();
}

/******************************************************************************/
function zmg_show_preview(type)
{
    zmg_render_preview(type);

    jQuery('#zmg_' + type + ' > div > a:eq(0)').removeClass('zmg_active');
    jQuery('#zmg_' + type + ' > div > a:eq(1)').addClass('zmg_active');

    jQuery('#zmg-money-extractor-' + type).hide();
    jQuery('#zmg_preview_' + type).show();
}

/******************************************************************************/
function zmg_render_preview(type)
{
    jQuery('#zmg_preview_' + type).html(
        zmg_replace_zmgtags(jQuery('#zmg-money-extractor-' + type).val())
    );
}

/******************************************************************************/
function zmg_replace_zmgtags(text)
{
    var path = 'http://images.zamango.com/preview/';
    var max_relgames = jQuery('#zmg-money-extractor-max_relgames').val();

    text = text.replace(/\[zmg:guid\]/g, "egypt-2");

    text = text.replace(/\[zmg:desc_250\]/g,
                        "<p>Egypt II: The Heliopolis " +
                        "Prophecy is a challenging point-and-click " +
                        "adventure</p>");

    text = text.replace(/\[zmg:description\]/g,
                        "<p>Help a young doctor, Tifet, stop the deadly " +
                        "disease that is afflicting his father! You`ll race " +
                        "against the clock as you try to prevent this " +
                        "illness from spreading through all of Heliopolis! " +
                        "This intense Large File Adventure game puts you " +
                        "in the middle of beautiful Egypt during its most " +
                        "prosperous time. Discover a deadly conspiracy and " +
                        "prevent it from occurring in Egypt II: The " +
                        "Heliopolis Prophecy!</p>");

    text = text.replace(/\[zmg:sysreq\]/g,
                        "<p><small>OS: Windows 2000/XP/Vista<br />" +
                        "CPU: 600 Mhz<br />RAM: 128 MB<br />DirectX: " +
                        "8.0<br />Hard Drive: 731 MB</small></p>");

    text = text.replace(/\[zmg:image50\]/g,  path + 'image_50.gif');
    text = text.replace(/\[zmg:image100\]/g, path + 'image.gif');
    text = text.replace(/\[zmg:image140\]/g, path + 'image_140.jpg');

    text = text.replace(/\[zmg:screenshot1\]/g, path + 'screenshot1.jpg');
    text = text.replace(/\[zmg:screenshot2\]/g, path + 'screenshot2.jpg');
    text = text.replace(/\[zmg:screenshot3\]/g, path + 'screenshot3.jpg');

    text = text.replace(/\[zmg:small_screenshot1\]/g, path + 'small_screenshot1.jpg');
    text = text.replace(/\[zmg:small_screenshot2\]/g, path + 'small_screenshot2.jpg');
    text = text.replace(/\[zmg:small_screenshot3\]/g, path + 'small_screenshot3.jpg');

    text = text.replace(/\[zmg:post_title\]/g, 'Egypt II: The Heliopolis Prophecy');

    var relgames = '<div class="zmg_relgames">';

    for (var i = 0; i < max_relgames; i++)
    {
        relgames +=
            '<div class="zmg_relgame_container">' +
              '<div class="zmg_relgame_item">' +
                '<a href="#" title="Egypt II: The Heliopolis Prophecy">' +
                  '<img src="' + path + 'relgame1.gif" alt="Egypt II: The Heliopolis Prophecy" />' +
                '</a>' +
                '<br />' +
                '<a href="#" title="Egypt II: The Heliopolis Prophecy">Egypt II: The Heliopolis Prophecy</a>' +
              '</div>' +
              '<div class="zmg_relgame_min"></div>' +
           '</div>';
    }

    relgames += '</div>';

    text = text.replace(/\[zmg:relgames\]/g, relgames);

    text = text.replace(/\[zmg:if_relgames\]/g, '');
    text = text.replace(/\[zmg:endif_relgames\]/g, '');

    text = text.replace(/\[zmg:button_bg\]/g, jQuery('#zmg-money-extractor-button_bg').val());
    text = text.replace(/\[zmg:button_src\]/g, jQuery('#zmg-money-extractor-button_src').val());

    text = text.replace(/\[zmg:buy_bg\]/g, jQuery('#zmg-money-extractor-buy_bg').val());
    text = text.replace(/\[zmg:buy_src\]/g, jQuery('#zmg-money-extractor-buy_src').val());

    text = text.replace(/\[zmg:if_download_pc\]/g, '<p>');
    text = text.replace(/\[zmg:endif_download_pc\]/g, '</p>');
    text = text.replace(/\[zmg:download_pc_link\]/g, '#');

    text = text.replace(/\[zmg:if_download_mac\]/g, '<p>');
    text = text.replace(/\[zmg:endif_download_mac\]/g, '</p>');
    text = text.replace(/\[zmg:download_mac_link\]/g, '#');

    text = text.replace(/\[zmg:if_buy_pc\]/g, '<p>');
    text = text.replace(/\[zmg:endif_buy_pc\]/g, '</p>');
    text = text.replace(/\[zmg:buy_pc_link\]/g, '#');

    text = text.replace(/\[zmg:if_buy_mac\]/g, '<p>');
    text = text.replace(/\[zmg:endif_buy_mac\]/g, '</p>');
    text = text.replace(/\[zmg:buy_mac_link\]/g, '#');

    text = text.replace(/\[zmg:if_play_online\]/g, '<p>');
    text = text.replace(/\[zmg:endif_play_online\]/g, '</p>');
    text = text.replace(/\[zmg:play_online_link\]/g, '#');

    return text;
}

/******************************************************************************/
function zmg_palette_change_bg(color, id)
{
    jQuery('#zmg_' + id + '_buttons li label img').css('background-color',
                                                       color);
    jQuery('#zmg-money-extractor-' + id + '_bg').val(color);

    zmg_render_preview('template');
    zmg_render_preview('excerpt');
}

/******************************************************************************/
function zmg_palette_display(id)
{
    for (var j = 0; j < 8; j++)
    {
        for (var i = 0; i < 255; i += 11)
        {
            jQuery('<span>&nbsp;</span>')
                .css('background-color', zmg_palette_RGB(i, j))
                .appendTo('#zmg_' + id + '_palette')
                .click(function() {
                    zmg_palette_change_bg(
                        zmg_palette_rgb2hex(
                            jQuery(this).css('background-color')
                        ), id
                    );
                });
        }
    }

    jQuery('#zmg_' + id + ' img')
        .css('background-color', jQuery('#zmg_' + id + '_bg').val());
}

/******************************************************************************/
function zmg_palette_rgb2hex(color)
{
    var rgb = (/rgb\((\d+),\s?(\d+),\s?(\d+)\)/i).exec(color);

    if (rgb)
    {
        var hex =
            ['0','1','2','3','4','5','6','7','8','9','A','B','C','D','E','F'];
        var res = '#';

        for (var i = 1; i < rgb.length; i++)
        {
            var j = parseInt(rgb[i]);
            res += hex[parseInt(j / 16)] + hex[j % 16];
        }

        return res;
    }
    else
        return color;
}

/******************************************************************************/
function zmg_palette_RGB(i, j)
{
    switch (j)
    {
    case 0:
        return "rgb(" + i + ", 0, 0)";
        break;
    case 1:
        return "rgb(255, " + i + ", 0)";
        break;
    case 2:
        return "rgb(" + (255 - i) + ", 255, 0)";
        break;
    case 3:
        return "rgb(0, " + (255 - i) + ", 0)";
        break;
    case 4:
        return "rgb(0, 0, " + i + ")";
        break;
    case 5:
        return "rgb(0, " + i + ", 255)";
        break;
    case 6:
        return "rgb(" + i + ", 255, 255)";
        break;
    case 7:
        return "rgb(255, " + (255 - i) + ", 255)";
        break;
    }
}

