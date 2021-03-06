<?php
/*
 * Content.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

use \Zend\Db\Sql\Sql;

class Content extends Model
{
    /*
     * Known content types.
     *
     * Note that this is a mirror of the content_types
     * table, however, because there should be relatively
     * few content types it makes sense to just hard code
     * it here as well (for now).
     *
     * @var array
     */
    static protected $_contentTypes = array(
        1 => 'Post',
        2 => 'File',
        3 => 'Folder',
    );

    /*
     * Our loaded tier information, if we have it.  This will
     * just be the ID's.
     *
     * @var array
     */
    protected $_tierIds = false;

    /*
     * Get known content types.
     *
     * @return array
     */
    public static function getContentTypes()
    {
        return self::$_contentTypes;
    }

    /*
     * Get content type string of this Conent.
     *
     * @return string
     */
    public function getContentType()
    {
        return self::$_contentTypes[$this->_values['type_id']];
    }

    /*
     * Set content type from a string.
     *
     * @param string
     * @return this
     *
     * @throws \Zend\Db\Exception\UnexpectedValueException
     * If we don't know the type being passed.
     */
    public function setContentType($type)
    {
        foreach(self::$_contentTypes as $key => $val) {
            if($val == $type) {
                $this->_values['type_id'] = $key;
                return $this;
            }
        }

        throw new \Zend\Db\Exception\UnexpectedValueException("Unknown content type: {$type}");
    }

    /*
     * Get the tier ID's associated with this piece of content.
     *
     * May be empty.
     *
     * @return array
     */
    public function getTierIds()
    {
        if($this->_tierIds === false) {
            $contentTable = Factory::getInstance()->get('Content');
            $select = (new Sql($contentTable->getAdapter(), 'sd_content_tiers_link'))
                        ->select()
                        ->where(array('content_id' => $this->_values['id']));

            $this->_tierIds = array();

            foreach($contentTable->selectWith($select) as $row) {
                $this->_tierIds[] = $row->tier_id;
            }
        }

        return $this->_tierIds;
    }

    /*
     * Generate the 'path' for a given file.  This is the DB-expensive
     * way to do it, so only use it if strictly necessary.  This path
     * will NOT include the file's ID on the end, just the parent path.
     *
     * @param User - for permissions checking
     * @param Profile - for permissions checking
     * @return string
     */
    public function generateParentPath($user, $profile)
    {
        if(!$this->getParentId()) {
            return '';
        }

        $parent = Factory::getInstance()->get('Content')
                  ->fetchProfileContentForUser($user, $profile,
                                                array('sd_content.id' => $this->getParentId()));


        if(!count($parent)) {
            // this is an error.
            throw new \Exception('Directory path does not exist, or you do not have permissions.');
        }

        return $parent[0]->generateParentPath($user, $profile) . $this->getParentId() . '/';
    }

    /*
     * Set an array of values (update) on all children belonging to
     * this item.
     *
     * Thie can be done recursively as well.
     *
     * @param array of key-value pairs for update statement
     * @param array tier settings to set
     * @param recursive? boolean
     *
     * This should never fail, but might throw DB exceptions in some
     * rare case.
     */
    public function updateChildren($values, $tiers, $recursive = false)
    {
        $contentTable = Factory::getInstance()->get('Content');
        $ctLinkTable = Factory::getInstance()->get('ContentTiersLink');

        // Update sub-content
        $contentTable->update($values, array('parent_id' => $this->getId()));

        // Grab sub-content to set tier info
        $subs = $contentTable->fetchByParentId($this->getId());

        foreach($subs as $content) {
            $ctLinkTable->delete(array(
                                    'content_id' => $content->getId(),
            ));

            foreach($tiers as $tierId) {
                $ctLinkTable->insert(array(
                                    'content_id' => $content->getId(),
                                    'tier_id' => $tierId,
                ));
            }

            if($recursive && ($content->getTypeId() == 3)) {
                $content->updateChildren($values, $tiers, true);
            }
        }
    }

    /*
     * isImage
     *
     * Returns boolean if it's a loadable image ... which is like
     * everything mime_type image/ except TIFF.
     *
     * @return boolean
     */
    public function isImage()
    {
        $meta = $this->getMimeType();

        if((substr($meta, 0, 5) == 'image') &&
           ($meta != 'image/tiff')) {
            return true;
        }

        return false;
    }

    /*
     * isDirectory
     *
     * Cause I'm checking for typeId == 3 everywhere
     *
     * @return boolean
     */
    public function isDirectory()
    {
        return ($this->_values['type_id'] == 3);
    }
}
