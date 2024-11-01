jQuery(document).ready(zamango_hack);

/******************************************************************************/
function zamango_hack ()
{
    jQuery("#toplevel_page_zmg-plugins li:first").remove();
    jQuery("#toplevel_page_zmg-plugins li:first").addClass('wp-first-item');
    jQuery("#toplevel_page_zmg-plugins .wp-first-item a")
        .addClass('wp-first-item');
}
