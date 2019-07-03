<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/ZendSkeletonApplication for the canonical source repository
 * @copyright Copyright (c) 2005-2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 */

namespace Swaggerdile\Controller;

use Swaggerdile\Controller;

use Patreon\API;
use Patreon\OAuth;

class PatreonController extends Controller
{

    /*
     * patreon linkage action
     *
     * Handles the linkage of patreon to swaggerdile for individual users.
     * Can have a "return" parameter which is a link to return to after
     * doing the linkage.
     *
     * On the return trip from Patreon, there will be a 'code' and a 
     * 'state'
     */
    public function indexAction()
    {
        // get view
        $view = $this->getView();
        $view->failure = false;

        // get our user
        $user = $this->getUser();
        $request = $this->getRequest();

        // If the user is not logged in, let's just kick them straight
        // to the view script.
        if(!is_object($user)) {
            // pass return
            $view->return = $request->getPost('return', $request->getQuery('return', ''));
            return $view;
        }

        // Get our profile info right off the bat - either from patreonState
        // or from return
        $return = $request->getPost('return', $request->getQuery('return', ''));
        $patreonCode = $request->getPost('code', $request->getQuery('code', ''));
        $patreonState = $request->getPost('state', $request->getQuery('state', ''));

        // We'll need this either way
        $patreonValidate = $this->getModel()->get('PatreonValidate');
        $profiles = $this->getModel()->get('Profiles');
        $config = $this->getConfig();

        // We'll need this a couple places
        $profile = false;

        if(strlen($patreonCode) && strlen($patreonState)) {
            // validate it.
            $ret = $patreonValidate->select(array(
                                            'user_id' => $user->getId(),
                                            'validate_token' => $patreonState,
            ));

            // this is a sadness
            if(!count($ret)) {
                raise \Exception("User took too long to validate, or provided invalid validate_token.");
            }

            // get our profile
            $profile = $profiles->fetchById($ret->current()->profile_id);

            // USE GLOBAL PATREON ID INSTEAD OF SPECIFIC CREDS
            $oauth = new OAuth( $config['patreon_client_id'],
                                $config['patreon_client_secret']);

            /* PER PROFILE CREDS - not as desirable
            $oauth = new OAuth($profile->getPatreonClientId(),
                               $profile->getPatreonClientSecret());
             */

            try {
                $tokens = $oauth->get_tokens($patreonCode, 
                                             $this->url()->fromRoute('patreon', array(), array('force_canonical' => true)));

                if(array_key_exists('error', $tokens) ||
                   (!array_key_exists('access_token', $tokens))) {
                    throw \Exception('failed');
                }

                // Try to grab user info
                $api = new API($tokens['access_token']);
                $patreonUser = $api->fetch_user();

                // Try to link them
                $patreonId = (int)$patreonUser['data']['id'];
                $patreonEmail = $patreonUser['data']['attributes']['email'];

                $patreonUsers = $this->getModel()->get('PatreonUsers');

                if(is_object($patreonUsers->fetchById($patreonId))) {
                    $patreonUsers->update(array(
                            'email' => $patreonEmail,
                            'user_id' => $user->getId(),
                        ), array(
                            'id' => $patreonId
                        ));
                } else {
                    $patreonUsers->insert(array(
                            'email' => $patreonEmail,
                            'user_id' => $user->getId(),
                            'id' => $patreonId
                        ));
                }

                $this->_rerunPatreon($profile->getId());

                return $this->redirect()->toRoute('profiles', array('profile' => $profile->getUrl()));
            } catch(\Exception $e) {
                $view->failure = true;
                return $view;
            }
        } elseif(!strlen($return)) {
            return $this->redirect()->toRoute('home');
        } else {
            $ret = $profiles->fetchByUrl($return);

            if(!count($ret)) {
                return $this->notFoundAction();
            }

            $profile = $ret[0];
        }

        // $view->patreon_client_id = $profile->getPatreonClientId();
        $view->patreon_client_id = $config['patreon_client_id'];

        // Generate a new Patreon ID if we need it
        $view->validate_token = $patreonValidate->fetchNewId($user, $profile);

        return $view;
    }
}
