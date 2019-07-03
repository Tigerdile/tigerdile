<?php
/*
 * Comments.php
 *
 * Helper to render comments form on different pages, and to render
 * comments for display.
 *
 * @author sconley
 */

namespace Swaggerdile\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Swaggerdile\Form\Comment;


class Comments extends AbstractHelper
{
    /*
     * Service Manager
     *
     * @var ServiceManager
     */
    protected $_sm = null;


    /*
     * Constructor
     *
     * Get the service manager
     *
     * @param ServiceManager
     */
    public function __construct($sm)
    {
        $this->_sm = $sm;
    }

    /*
     * Invoke to render comments for a given Content object.
     *
     * @param Content
     * @param boolean - Is owner?
     *
     * @return string
     */
    public function __invoke($profile, $content)
    {
        // Comments enabled?  If not, return nothing.
        if((!is_object($content) || $content->getIsCommentsDisabled())) {
            return '';
        }

        $view = $this->getView();

        $commentsTable = $this->_sm->getServiceLocator()
                              ->get('Model')->get('Comments');

        $comments = $commentsTable->setOrderBy('sd_comments.id asc')
                                  ->fetchByContentId($content->getId());

        // @TODO : Inefficient, second user load.  Dunno how to
        // do this better right now
        $userTable = $this->_sm->getServiceLocator()
                              ->get('Model')->get('User');

        $userId = $this->_sm->getServiceLocator()->get('AuthService')
                            ->getIdentity();
        $user = null;

        if((!is_object($userId)) && ($userId)) {
            $user = $userTable->fetchById($userId);
        }

        // Sort them into trees
        $parentComments = array();
        $childCommentsByParent = array();

        foreach($comments as $comment) {
            $parentId = (int)$comment->getParentId();

            if(!$parentId) {
                $parentComments[] = $comment;
            } else {
                if(array_key_exists($parentId, $childCommentsByParent)) {
                    $childCommentsByParent[$parentId][] = $comment;
                } else {
                    $childCommentsByParent[$parentId] = array($comment);
                }
            }
        }

        // Grab our comment form
        $form = new Comment();

        return $view->render('swaggerdile/profile/comments', array(
            'parentComments' => $parentComments,
            'childCommentsByParent' => $childCommentsByParent,
            'content' => $content,
            'form' => $form,
            'profile' => $profile,
            'user' => $user,
        ));
    }
}
