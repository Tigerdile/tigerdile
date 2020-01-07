/*
 *
 * This is a javascript library that detects and runs the 'correct' kind of
 * video and implements all our JavaScript-side features.  Implementing this
 * stuff in JS is key because we have people that want to embed streams on
 * web pages; this library gives them the flexibility to do that.
 *
 * Dependencies:
 *
 * - jQuery
 * - FLV.js
 * - video.js
 * - Video JS must be configured with videojs.options.flash.swf going to
 *   its flash loader.
 *
 * This source code is public domain with no warranty or promise that it
 * does anything useful.
 *
 * By: Tigerdile, LLC
 */

ff_error_shown = false;

var tdvideo = {
    detectMobile: function() {
        /* This is from http://detectmobilebrowsers.com/
         *
         * It seems to still work, all these years later.
         *
         * Returns boolean if we think its a mobile browser.
         */
        var a = (navigator.userAgent||navigator.vendor||window.opera);
        return (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(a)||/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i.test(a.substr(0,4)));
    },
    detectFlash: function() {
        /* I found this off stack exchange, from a URL I don't have anymore.
         * Credit goes to someone smarter than me :)
         *
         * Returns boolean if flash is enabled.
         */
        return navigator.plugins.namedItem('Shockwave Flash');
    },
    detectFlvjs: function() {
        /* This returns true if flvjs is able to run.
         */
        var features = flvjs.getFeatureList();

        return features['mseLiveFlvPlayback'];
    },
    loadVideo: function(id, flvjs_url, rtmp_url, hls_url, volume, autoplay) {
        /* Assuming there is a <video> tag with id 'id', we will attempt
         * to load a video in that spot.
         *
         * Parameters:
         *
         * id - video element ID
         * flvjs_url - The URL to the FLV JS version of the stream.
         * rtmp_url - The URL to the rtmp version of the stream
         * hls_url - the URL to the HLS version of the stream.
         *
         * Any URL that is blank will not be used, so you can pass an empty
         * string in for a technology you want to skip.
         *
         * Returns the loaded player object, whichever kind it may be.
         */

        // defaults
        if(volume == undefined) {
            volume = 1;
        }

        if(autoplay == undefined) {
            autoplay = true;
        }

        // Prefer Flash, if available
        if(rtmp_url && tdvideo.detectFlash()) {
            var video = videojs(id, {
                autoplay: autoplay,
                controls: true,
                fluid: true,
                sources: [
                    {
                        src: rtmp_url,
                        type: 'rtmp/mp4'
                    }
                ],
                techOrder: ['flash']
            }, function() {
                // on ready
                video.volume(volume);
            });

            return video;
        }

        // Prefer FLV.js next, if available
        if(flvjs_url && tdvideo.detectFlvjs() && (!tdvideo.detectMobile())) {
            var videoElement = document.getElementById(id);
            var flvPlayer = flvjs.createPlayer({
                type: 'flv',
                isLive: true,
                withCredentials: true,
                cors: true,
                url: flvjs_url
            });

            // Set up handlers.
            flvPlayer.on(flvjs.Events.ERROR, function(e) {
                // Media error happens whenever the media stops.
                // ignore those
                if (e == 'MediaError') {
                    return;
                }

                //setTimeout(1000, function() {
                    tdvideo.loadVideo(id, flvjs_url, rtmp_url, hls_url, volume,
                                      autoplay);
                //});
            });

            flvPlayer.on(flvjs.Events.LOADING_COMPLETE, function(){
                // TODO: Put banner image back up?  Resume play?
                tdvideo.loadVideo(id, flvjs_url, rtmp_url, hls_url, volume,
                                  autoplay);
            });

            flvPlayer.attachMediaElement(videoElement);
            flvPlayer.load();

            // Set volume
            videoElement.volume = volume;

            // Start playing
            if(autoplay) {
                var promise = flvPlayer.play();
                //flvPlayer.play();

                if (promise !== undefined) {
                    promise.then(_ => {
                        // Autoplay started!
                    }).catch(error => {
                        if (!ff_error_shown) {
                            alert("Firefox (and other browsers) now block autoplay of video.  If you click the spot next to your URL that has a little green lock box and a few other icons, you can give Tigerdile permission to autoplay.  Otherwise, you'll need to click the play button on the video itself.  Sorry!");
                            ff_error_shown = true;
                        }
                    });
                }
            }

            return flvPlayer;
        }

        // HLS is the ultimate fallback, unless it wasn't provided either.
        if(!hls_url) {
            alert("You have probably selected 'Force Flash' mode when you do not have flash installed, or Flash is disabled.  Sometimes you need to click a little puzzle piece in the header of your browser to turn on Flash.  If you believe you have gotten this message, contact Tigerdile Support at: support@tigerdile.com");
            return null;
        }

        var video = videojs(id, {
            autoplay: autoplay,
            controls: true,
            fluid: true,
            sources: [
                {
                    src: hls_url,
                    type: 'application/x-mpegURL'
                }
            ],
            techOrder: ['html5'],
            html5: {
                hls: {
                    withCredentials: true
                }
            }
        }, function() {
            // on ready
            video.volume(volume);
        });

        return video
    }

}
