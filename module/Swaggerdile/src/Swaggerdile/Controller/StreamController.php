<?php
/*
 * StreamController
 *
 * Handles routing for Tigerdile streams, and the stream listing page.
 *
 * @author sconley
 */

namespace Swaggerdile\Controller;

use Swaggerdile\Controller;
use Swaggerdile\Form\Stream as StreamForm;


class StreamController extends Controller
{
    /*
     * Stream listing page
     */
    public function indexAction()
    {
        return $this->getView();
    }

    /*
     * Individual stream page
     *
     * Possible parameters:
     * - rtmpt
     * - lowBandwidth
     * - single
     * - password
     * - forceFlash
     * - forceFlashless
     * - popout
     */
    public function streamAction()
    {
        $streamUrl = $this->params()->fromRoute('stream', false);

        // This shouldn't happen, but if it does...!
        if($streamUrl === false) {
            return $this->indexAction();
        }

        // Try to load it.
        $streams = $this->getModel()->get('Profiles')
                                    ->fetchByUrl($streamUrl);

        // This should be exactly 1 if we've found one.
        if(count($streams) != 1) {
            // this is an error
            if(!empty($streams)) {
                $this->getLogger()->error(
                    "Got more than one stream for URL: {$streamUrl}"
                );
            }

            return $this->notFoundAction();
        }

        $stream = $streams[0];

        // Load up our stream settings.
        $streamSettings = array();

        // Get our parameters
        $request = $this->getRequest();
        $streamSettings['rtmpt'] = (int)$request->getPost('rtmpt',
                                        $request->getQuery('rtmpt', 0)
        );

        $streamSettings['lowBandwidth'] = (int)$request->getPost('lowBandwidth',
                                               $request->getQuery(
                                                    'lowBandwidth', 0)
        );

        $single = (int)$request->getPost('single',
                                         $request->getQuery('single', 0)
        );


        $streamSettings['single'] = $single;
        $streamSettings['password'] = $request->getPost('password',
                                      $request->getQuery('password', ''));

        $streamSettings['forceFlash'] = $request->getPost('forceFlash',
                                        $request->getQuery('forceFlash', 0));

        $streamSettings['forceFlashless'] = $request->getPost('forceFlashless',
                                        $request->getQuery('forceFlashless', 0));

        $popout = (int)$request->getPost('popout', $request->getQuery('popout', 0));

        if($popout) {
            $this->layout('layout/popout');
        }

        $view = $this->getView();

        $view->authorized = true;
        $view->popout = $popout;

        // Do we need a passowrd?
        $necessaryPassword = $stream->getViewerPassword();
        if(strlen($necessaryPassword) &&
           ($streamSettings['password'] != $necessaryPassword)) {
            // Not authorized.
            $view->authorized = false;
        }

        $view->stream = $stream;

        // Are we multi-streaming?
        if(!$single) {
            $streamSettings['multi'] = $stream->getMultiStreams();
        }

        $view->streamSettings = $streamSettings;


        return $view;
    }

    /*
     * Edit stream page
     */
    public function editAction()
    {
        // Must be logged in, and either admin or page owner.
        $user = $this->getUser();

        $streamUrl = $this->params()->fromRoute('stream', false);

        // This shouldn't happen, but if it does...!
        if($streamUrl === false) {
            return $this->indexAction();
        }

        // Try to load it.
        $profilesTable = $this->getModel()->get('Profiles');
        $streams = $profilesTable->fetchByUrl($streamUrl);

        // This should be exactly 1 if we've found one.
        if(count($streams) != 1) {
            // this is an error
            if(!empty($streams)) {
                $this->getLogger()->error(
                    "Got more than one stream for URL: {$streamUrl}"
                );
            }

            return $this->notFoundAction();
        }

        $stream = $streams[0];

        // Check ownership
        if((!is_object($user)) ||
           (!(($user->isAdmin()) || ($user->getId() == $stream->getOwnerId())))) {
            // Such nope
            return $this->notFoundAction();
        }

        // The form
        $form = new StreamForm();
        $request = $this->getRequest();

        // Get view
        $view = $this->getView();

        // URL failure -- if true, our URL is a duplicate.
        $view->urlFailure = false;
        $view->imageErrors = array();

        $view->stream = $stream;
        $view->form = $form;
        $view->openTab = 'settings';

        // Fetch our rebroadcast entries.
        $rebTable = $this->getModel()->get('Rebroadcasts');

        $rebroads = $rebTable->fetchByProfileId($stream->getId());
        $activeRebroads = array();

        foreach($rebroads as $r) {
            if($r->getIsEnabled()) {
                $activeRebroads[] = $r;
            }
        }

        if($request->isPost()) {
            $rebroadAction = '';
            $rebroadId = '';

            // What tab did we save on?
            foreach($request->getPost() as $key => $val) {
                if(substr($key, 0, 4) == 'act-') {
                    // Detect Enable/Disalbe/Delete on the
                    // rebroadcast screen
                    if(substr($key, 0, 16) == 'act-rebroadcast-') {
                        $view->openTab = 'rebroadcast';
                        $rebroadId = substr($key, 16);
                        $rebroadAction = $val;
                    } else {
                        $view->openTab = substr($key, 4);
                    }
                }
            }

            $form->setData($request->getPost());

            if($form->isValid()) {
                $data = $form->getData();

                // Check our URL
                $res = $profilesTable->fetchByUrl($data['url']);

                if((!count($res)) || ($res[0]->getId() == $stream->getId())) {
                    // Save our media files, if we have them.
                    $files = $request->getFiles();

                    // we may have files
                    if(count($files)) {
                        // Files to check
                        $checkFiles = array(
                            'vipImage' => 'vip', 
                            'offlineImage' => 'stream-offline',
                            'backgroundImage' => 'stream-background',
                            'donateImage' => 'donate',
                        );

                        foreach(array_keys($checkFiles) as $file) {
                            if(array_key_exists($file, $files) &&
                               ($files[$file]['size'] > 0)) {
                                // try to store it.
                                try {
                                    \Swaggerdile\Media::storePublicImage(
                                                    $stream,
                                                    $files[$file]['tmp_name'],
                                                    $checkFiles[$file]
                                    );
                                } catch(\Exception $e) {
                                    $view->imageErrors[$file] = $e->getMessage();
                                }
                            }

                            // short circuit on image errors.
                            if(count($view->imageErrors)) {
                                return $view;
                            }
                        }
                    }

                    // Try to save it
                    $profilesTable->beginTransaction();

                    // What are the fields we want to copy over?
                    $profileFields = array(
                        'title', 'stream_blurb', 'url', 'rtmp_password',
                        'viewer_password', 'is_nsfw', 'aspect_ratio',
                        'is_allow_guests', 'donation_email',
                    );

                    // Filter these
                    $filterFields = array(
                        'above_stream_html', 'below_stream_html',
                    );

                    $toUpdate = array();

                    foreach($profileFields as $field) {
                        $toUpdate[$field] = $data[$field];
                    }

                    // Pull the images out
                    foreach($filterFields as $field) {
                        $toUpdate[$field] = \Swaggerdile\Media::filterInlineData($stream, $data[$field]);
                    }

                    $profilesTable->update(
                                        $toUpdate,
                                        array(
                                            'id' => $stream->getId()
                                        )
                    );

                    // Add any new rebroadcasts.  Default the is enabled
                    // appropriately
                    $isEnabled = 
                        (
                            ($stream->getStreamTypeId() == 2) &&
                            (count($activeRebroads) < 2)
                        ) || (
                            ($stream->getStreamTypeId() > 2));

                    if($request->getPost('rebroadcast_title') &&
                       $request->getPost('rebroadcast_url')) {
                        $rebTable->insert(array(
                            'profile_id' => $stream->getId(),
                            'title' => $request->getPost('rebroadcast_title'),
                            'target_url' => $request->getPost('rebroadcast_url'),
                            'stream_key' => $request->getPost('rebroadcast_key'),
                            'is_enabled' => $isEnabled,
                        ));

                        // add a blank one in
                        if($isEnabled) {
                            $activeRebroads[] = array();
                        }
                    }

                    // Delete, enable, or disable
                    switch($rebroadAction) {
                        case 'Enable':
                            if(($stream->getStreamTypeId() == 3) ||
                               (($stream->getStreamTypeId() == 2) &&
                                (count($activeRebroads) < 2))) {
                                    $rebTable->update(
                                        array(
                                            'is_enabled' => 1,
                                        ),
                                        array(
                                            'id' => $rebroadId,
                                            'profile_id' => $stream->getId(),
                                        )
                                    );
                            }

                            break;
                        case 'Disable':
                            $rebTable->update(
                                array(
                                    'is_enabled' => 0,
                                ),
                                array(
                                    'id' => $rebroadId,
                                    'profile_id' => $stream->getId(),
                                )
                            );

                            break;
                        case 'Delete':
                            $rebTable->delete(
                                array(
                                    'id' => $rebroadId,
                                    'profile_id' => $stream->getId(),
                                )
                            );
                            break;
                        default:
                    }

                    $profilesTable->commitTransaction();

                    // It's kind of in-efficient to reload this stuff
                    // all the time, but I rather not duplicate code
                    // and I'm too lazy to do this a cleaner way for
                    // code that will infrequently see action.
                    $activeRebroads = array();
                    $rebroads = $rebTable->fetchByProfileId($stream->getId());

                    foreach($rebroads as $r) {
                        if($r->getIsEnabled()) {
                            $activeRebroads[] = $r;
                        }
                    }

                    // Poke chat
                    $this->getLocator()->get('Chat')
                         ->refreshChannel($stream->getId());
                } else {
                    $view->urlFailure = true;
                }
            }
        } else {
            $data = array(
                'title' => $stream->getTitle(),
                'stream_blurb' => $stream->getStreamBlurb(),
                'url' => $stream->getUrl(),
                'rtmp_password' => $stream->getRtmpPassword(),
                'viewer_password' => $stream->getViewerPassword(),
                'is_nsfw' => $stream->getIsNsfw(),
                'above_stream_html' => $stream->getAboveStreamHtml(),
                'aspect_ratio' => $stream->getAspectRatio(),
                'below_stream_html' => $stream->getBelowStreamHtml(),
                'donation_email' => $stream->getDonationEmail(),
                'is_allow_guests' => $stream->getIsAllowedGuests(),
            );

            $form->setData($data);
        }

        $view->rebroadcasts = $rebroads;
        $view->activeRebroads = $activeRebroads;
        return $view;
    }

    /*
     * Export an OBS profile ZIP file for a given stream.
     */
    public function obsexportAction()
    {
        // Must be logged in, and either admin or page owner.
        $user = $this->getUser();

        $streamUrl = $this->params()->fromRoute('stream', false);

        // This shouldn't happen, but if it does...!
        if($streamUrl === false) {
            return $this->indexAction();
        }

        // Try to load it.
        $streams = $this->getModel()->get('Profiles')
                                    ->fetchByUrl($streamUrl);

        // This should be exactly 1 if we've found one.
        if(count($streams) != 1) {
            // this is an error
            if(!empty($streams)) {
                $this->getLogger()->error(
                    "Got more than one stream for URL: {$streamUrl}"
                );
            }

            return $this->notFoundAction();
        }

        $stream = $streams[0];

        // Check ownership
        if((!is_object($user)) ||
           (!(($user->isAdmin()) || ($user->getId() == $stream->getOwnerId())))) {
            // Such nope
            return $this->notFoundAction();
        }

        // Build our file
        $zip = new \ZipStream\ZipStream();

        // What's our apsect ratio?
        if($stream->getAspectRatio() == 1) {
            $outputX = 960;
            $outputY = 540;
        } else {
            $outputX = 800;
            $outputY = 600;
        }

        // capture it
        ob_start();
        $zip->addFile("Tigerdile/basic.ini",<<<EOS
[General]
Name=Tigerdile

[Output]
Mode=Advanced

[Video]
FPSCommon=20
OutputCX={$outputX}
OutputCY={$outputY}
EOS
        );

        $password = urlencode($stream->getRtmpPassword());

        $zip->addFile("Tigerdile/service.json",<<<EOS
{
    "settings": {
        "key": "{$stream->getUrl()}",
        "server": "rtmp://stream.tigerdile.com:1935/live?token={$password}"
    },
    "type": "rtmp_custom"
}
EOS
        );

        $zip->addFile("Tigerdile/streamEncoder.json",<<<EOS
{
    "bitrate": 700,
    "rate_control": "VBR"
}
EOS
        );

        $zip->finish();

        $zipfile = ob_get_contents();
        ob_end_clean();

        // And push it!
        $response = $this->getResponse();
        $response->setStatusCode(200);
        $response->setContent($zipfile);

        $headers = new \Zend\Http\Headers();
        $headers->addHeaderLine('Content-Type', 'application/x-zip')
                ->addHeaderLine('Content-Length', strlen($zipfile))
                ->addHeaderLine('Content-Transfer-Encoding', 'binary')
                ->addHeaderLine('Content-Disposition', 'attachment; filename="Tigerdile-OBS-Profile.zip"');

        $response->setHeaders($headers);

        return $response;
    }

    /*
     * This is the setup wizard for setting up a stream.
     */
    public function setupAction()
    {
        // Must be logged in and approved to stream.
        $user = $this->getUser();

        if((!is_object($user)) || (!$user->getMeta('stream_approved', true))) {
            return $this->notFoundAction();
        }
 
        // if have a profile, kick them to edit
        $profiles = $user->getProfiles();

        if(count($profiles)) {
            return $this->redirect()
                        ->toRoute('stream/detail/edit', array('stream' => $profiles[0]->getUrl()));
        }

        // Errors
        $hasErrors = false;

        $view = $this->getView();
        $view->urlError = false;
        $view->done = false;

        // get our table.
        $profilesTable = $this->getModel()->get('Profiles');

        // The form
        $form = new StreamForm();
        $request = $this->getRequest();

        if($request->isPost()) {
            $form->setData($request->getPost());

            if($form->isValid()) {
                $data = $form->getData();

                // Create the stream
                $profilesTable->beginTransaction();

                // Make sure URL is still available.
                $profilesWithUrl = $profilesTable->fetchByUrl($data['url']);

                if(count($profilesWithUrl)) {
                    $view->urlError = true;
                    $hasErrors = true;
                } else {
                    unset($data['act']);

                    $profilesTable->insert(array(
                        'title' => $data['title'],
                        'owner_id' => $user->getId(),
                        'created' => date('Y-m-d H:i:s'),
                        'is_visible' => 1,
                        'is_nsfw' => $data['is_nsfw'],
                        'url' => $data['url'],
                        'use_watermark' => 0,
                        'is_hiatus' => 0,
                        'is_allow_guests' => $data['is_allow_guests'],
                        'rtmp_password' => $data['rtmp_password'],
                        'stream_blurb' => $data['stream_blurb'],
                        'aspect_ratio' => $data['aspect_ratio'],
                        'viewer_password' => $data['viewer_password'],
                        'is_tigerdile_friend' => 0,
                    ));
                    $view->done = true;

                    $profiles = $profilesTable->fetchByOwnerId($user->getId());
                    $view->stream = $profiles[0];
                }

                $profilesTable->commitTransaction();
            } else {
                $hasErrors = true;
            }
        } else {
            // Suggest a URL
            $potentialUrl = strtolower(preg_replace('/[^\d\w_-]/', '-', $user->getUserLogin()));
            $potentialUrl = $profilesTable->generateUniqueUrl($potentialUrl);

            $form->get('url')->setValue($potentialUrl);
        }

        $view->hasErrors = $hasErrors;
        $view->form = $form;
        return $view;
    }
}
