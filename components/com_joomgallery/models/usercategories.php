<?php
// $HeadURL: https://joomgallery.org/svn/joomgallery/JG-2.0/JG/trunk/components/com_joomgallery/models/usercategories.php $
// $Id: usercategories.php 4382 2014-05-05 17:20:15Z erftralle $
/****************************************************************************************\
**   JoomGallery 2                                                                      **
**   By: JoomGallery::ProjectTeam                                                       **
**   Copyright (C) 2008 - 2012  JoomGallery::ProjectTeam                                **
**   Based on: JoomGallery 1.0.0 by JoomGallery::ProjectTeam                            **
**   Released under GNU GPL Public License                                              **
**   License: http://www.gnu.org/copyleft/gpl.html or have a look                       **
**   at administrator/components/com_joomgallery/LICENSE.TXT                            **
\****************************************************************************************/

defined('_JEXEC') or die('Direct Access to this location is not allowed.');

/**
 * JoomGallery User Categories Model
 *
 * @package JoomGallery
 * @since   1.5.5
 */
class JoomGalleryModelUsercategories extends JoomGalleryModel
{
  /**
   * Categories data array
   *
   * @var     array
   */
  protected $_categories;

  /**
   * Categories number
   *
   * @var     int
   */
  protected $_total = null;

  /**
   * Number of categories that the current user owns
   *
   * @var     int
   */
  protected $_categoryNumber = null;

  /**
   * Constructor
   *
   * @return  void
   * @since   1.5.5
   */
  public function __construct()
  {
    parent::__construct();
  }

  /**
   * Retrieve the category data
   *
   * @return  array     Array of objects containing the category data
   * @since   1.5.5
   */
  public function getCategories()
  {
    if($this->_loadCategories())
    {
      return $this->_categories;
    }

    return array();
  }

  /**
   * Method to get the total number of categories
   *
   * @return  int     The total number of categories
   * @since   1.5.5
   */
  public function getTotal()
  {
    // Let's load the data if it doesn't already exist
    if(empty($this->_total))
    {
      $query = $this->_buildQuery();
      $this->_total = $this->_getListCount($query);
    }

    return $this->_total;
  }

  /**
   * Returns the number of categories that the current user owns
   *
   * @return  int     The number of categories of categories that the current user owns
   * @since   2.1.6
   */
  public function getCategoryNumber()
  {
    if(empty($this->_categoryNumber))
    {
      $query = $this->_db->getQuery(true);
      $query->select('COUNT(cid)')
            ->from(_JOOM_TABLE_CATEGORIES)
            ->where('owner = '.$this->_user->get('id'));
      $this->_db->setQuery($query);
      $this->_categoryNumber = $this->_db->loadResult();
    }

    return $this->_categoryNumber;
  }

  /**
   * Loads the categories data from the database
   *
   * @return  boolean   True on success, false otherwise
   * @since   1.5.5
   */
  protected function _loadCategories()
  {
    // Let's load the data if it doesn't already exist
    if(empty($this->_categories))
    {
      jimport('joomla.filesystem.file');

      $query = $this->_buildQuery();

      // Get the pagination request variables
      $limit      = JRequest::getInt('limit', 0);
      $limitstart = JRequest::getInt('limitstart', 0);

      if(!$rows = $this->_getList($query, $limitstart, $limit))
      {
        return false;
      }

      $this->_categories = $rows;
    }

    return true;
  }

  /**
   * Returns the query to get the category rows from the database
   *
   * @return  string    The query to be used to retrieve the category rows from the database
   * @since   1.5.5
   */
  protected function _buildQuery()
  {
    $query = $this->_db->getQuery(true)
          ->select('c.cid, c.name, c.owner, c.thumbnail, c.parent_id, c.published, c.hidden')
          ->select('(SELECT COUNT(cid) FROM '._JOOM_TABLE_CATEGORIES.' AS b WHERE b.parent_id = c.cid) AS children')
          ->select('(SELECT COUNT(id) FROM '._JOOM_TABLE_IMAGES.' AS a WHERE a.catid = c.cid) AS images')
          ->from(_JOOM_TABLE_CATEGORIES.' AS c')
          ->where('parent_id > 0')

    // Join over the images for category thumbnail
          ->select('i.id, i.catid, i.imgthumbname, i.hidden AS imghidden')
          ->leftJoin(_JOOM_TABLE_IMAGES.' AS i ON (     c.thumbnail = i.id
                                                    AND i.published = 1
                                                    AND i.approved  = 1
                                                    AND i.access    IN ('.implode(',', $this->_user->getAuthorisedViewLevels()).'))');

    // Filter by state
    $filter = JRequest::getInt('filter', null);

    switch($filter)
    {
      case 1:
        // Published
        $query->where('c.published = 1');
        break;
      case 2:
        // Not published
        $query->where('c.published = 0');
        break;
      default:
        // No filter by state
        break;
    }

    // A Super User will see all categories if the correspondent backend option is enabled
    if(!$this->_config->get('jg_showallpicstoadmin') || !$this->_user->authorise('core.admin'))
    {
      $query->where('c.owner = '.$this->_user->get('id'));
    }

    $query->order('c.lft ASC');

    return $query;
  }
}