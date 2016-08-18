<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_user_relations
 *
 * @copyright 2004-2016 The Admidio Team
 * @see http://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableUserRelation
 * This class manages the set, update and delete in the table adm_user_relations
 */
class TableUserRelation extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_user_relation_types.
     * If the id is set than the specific message will be loaded.
     * @param \Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int       $id   The recordset of the relation with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(&$database, $id = 0)
    {
        parent::__construct($database, TBL_USER_RELATIONS, 'ure', $id);
    }

    /**
     * Returns the inverse relation.
     * 
     * @return TableUserRelation Returns the inverse relation
     */
    public function getInverse()
    {
        $relationtype = new TableUserRelationType($this->db, $this->getValue('ure_urt_id'));
        if ($relationtype->getValue('urt_id_inverse') == null)
        {
            return null;
        }
        $selectColumns = array('ure_urt_id'=> $relationtype->getValue('urt_id_inverse'),
            'ure_usr_id1'                  => $this->getValue('ure_usr_id2'), 'ure_usr_id2'=>$this->getValue('ure_usr_id1'));
        $inverse = new self($this->db);
        $inverse->readDataByColumns($selectColumns);
        if ($inverse->isNewRecord()) {
            return null;
        }
        return $inverse;
    }

    public function delete($deleteInverse = true)
    {
        $this->db->startTransaction();
        if ($deleteInverse)
        {
            $inverse = $this->getInverse();
            if ($inverse)
            {
                $inverse->delete(false);
            }
        }
        parent::delete();
        return $this->db->endTransaction();
    }
}
