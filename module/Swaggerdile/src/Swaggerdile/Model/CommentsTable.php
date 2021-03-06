<?php
/*
 * CommentsTable.php
 *
 * Auto-generated by modelBuild.py Model script
 *
 * @author sconley
 */

namespace Swaggerdile\Model;

class CommentsTable extends ModelTable
{
    /*
     * Define our datastore table Kind
     *
     * @param string
     */
    protected $table = 'sd_comments';

    /*
     * Override fetchByContentId so that we can include
     * author linkage.
     *
     * @param integer
     * @return array
     *
     * THIS METHOD RESPECTS ORDER BY and LIMIT/OFFSET
     */
    public function fetchByContentId($contentId)
    {
        $order = $this->getOrderBy();

        // also limit and offset
        $limit = $this->getLimit();
        $offset = $this->getOffset();

        $rowset = $this->select(function($select) use ($contentId, $order, $limit, $offset) {
            $select->join('tigerd_users',
                          'tigerd_users.ID = sd_comments.author_id',
                          array('display_name'))
                   ->where(array('sd_comments.content_id' => $contentId,
                                 'sd_comments.is_deleted' => 0));

            if(!empty($order)) {
                $select->order($order);
            }

            if($limit !== false) {
                $select->limit($limit);
            }

            if($offset !== false) {
                $select->offset($offset);
            }

            return $select;
        });

        return $this->_returnArray($rowset);
    }
}
