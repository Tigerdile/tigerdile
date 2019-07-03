/*
 * Url preview script 
 * powered by jQuery (http://www.jquery.com)
 * 
 * written by Alen Grakalic (http://cssglobe.com)
 * 
 * for more info visit http://cssglobe.com/post/1695/easiest-tooltip-and-image-preview-using-jquery
 *
 */
 
function screenshotPreview(){    
    /* CONFIG */

    xOffset = 200;
    yOffset = 30;

    // these 2 variable determine popup's distance from the cursor
    // you might want to adjust to get the right result

    /* END CONFIG */
    function turnOnScreenshot(e) {
        var ssWindow = jQuery('#screenshot');

        // We may need to reload what's in there
        if(e.type == "click") {
            if(ssWindow.length) {
                // Did we click the same link?  If so, remove and 
                // exit.  Otherwise, just remove
                var clicked = ssWindow.find('img').attr('src');
                ssWindow.remove();

                if(clicked == this.rel) {
                    return false;
                }
            }
        }

        if(!jQuery('#screenshot').length) {
            var p = jQuery('<p id="screenshot" />');
            var img = jQuery('<img alt="Preview" width="400" height="300" />');
            img.attr('src', this.rel);
            p.append(img);

            jQuery("body").append(p);
        }

        jQuery("#screenshot")
            .css("top",(e.pageY - xOffset) + "px")
            .css("left",(e.pageX + yOffset) + "px")
            .fadeIn("fast");                        

        return false;
    };

    function turnOffScreenshot() {
        jQuery("#screenshot").remove();
    }

    jQuery("#onlineTable a.screenshot").hover(turnOnScreenshot,
                                 turnOffScreenshot);

    jQuery("#onlineTable a.preview-click").click(turnOnScreenshot);

    jQuery("#onlineTable a.screenshot").mousemove(function(e){
        jQuery("#screenshot")
            .css("top",(e.pageY - xOffset) + "px")
            .css("left",(e.pageX + yOffset) + "px");
    });
};
