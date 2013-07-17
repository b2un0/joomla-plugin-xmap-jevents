<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */
 
defined('_JEXEC') or die;

require_once JPATH_SITE . '/components/com_jevents/jevents.defines.php';

final class xmap_com_jevents {

	public function getTree(&$xmap, &$parent, &$params) {
		$include_events = JArrayHelper::getValue($params, 'include_events', 1);
		$include_events = ($include_events == 1 || ($include_events == 2 && $xmap->view == 'xml') || ($include_events == 3 && $xmap->view == 'html'));
		$params['include_events'] = $include_events;
		
		$show_unauth = JArrayHelper::getValue($params, 'show_unauth');
		$show_unauth = ($show_unauth == 1 || ( $show_unauth == 2 && $xmap->view == 'xml') || ( $show_unauth == 3 && $xmap->view == 'html'));
		$params['show_unauth'] = $show_unauth;
		
		$params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());
		
		$priority = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
		$changefreq = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);
		
		if($priority == -1) {
			$priority = $parent->priority;
		}
		
		if($changefreq == -1) {
			$changefreq = $parent->changefreq;
		}
		
		$params['category_priority'] = $priority;
		$params['category_changefreq'] = $changefreq;
		
		$priority = JArrayHelper::getValue($params, 'event_priority', $parent->priority);
		$changefreq = JArrayHelper::getValue($params, 'event_priority', $parent->changefreq);
		
		if($priority == -1) {
			$priority = $parent->priority;
		}
		
		if($changefreq == -1) {
			$changefreq = $parent->changefreq;
		}
		
		$params['event_priority'] = $priority;
		$params['event_changefreq'] = $changefreq;

		// Backup the original datamodel to restore it later
		$datamodel = JevRegistry::getInstance('jevents')->getReference('jevents.datamodel', false);

		self::getCategoryTree ($xmap, $parent, $params, 1);

		// Restore the original datamodel to avoid problems with modules or plugins
		JevRegistry::getInstance('jevents')->setReference('jevents.datamodel', $datamodel);
		
		return true;
	}

	private static function getCategoryTree(&$xmap, &$parent, &$params, $parent_id) {
		$db = JFactory::getDBO();
		$my = JFactory::getUser();
	
		$query = $db->getQuery(true)
				->select(array('id', 'title', 'parent_id'))
				->from('#__categories')
				->where('extension = ' . $db->Quote('com_jevents'))
				->where('parent_id = ' . $db->quote($parent_id))
				->order('lft');
		
		if (!$params['show_unauth']) {
			$query->where('access IN(' . $params['groups'] . ')');
		}
		
		$db->setQuery($query);
		$rows = $db->loadObjectList();
		
		if(empty($rows)) {
			return;
		}

		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->title;
			$node->uid = $parent->uid . '_cid_' . $row->id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['category_priority'];
			$node->changefreq = $params['category_changefreq'];
			$node->pid = $row->parent_id;
			// TODO - Itemid fehlt
			$node->link = 'index.php?option=com_jevents&task=cat.listevents&offset=1&category_fv='.$row->id;
			
			if ($xmap->printNode($node) !== false) {
				self::getCategoryTree($xmap, $parent, $params, $row->id);
				if ($params['include_events']) {
					self::getEvents($xmap, $parent, $params, $row->id);
				}
			}
		}
		
		$xmap->changeLevel(-1);
	}

	private static function getEvents(&$xmap, &$parent, &$params, $catid) {
		static $datamodel;
		
		if (!$datamodel) {
			$datamodel = new JEventsDataModel;
		}
		
		// TODO - hier stimmt was bei der Übergabe der catid nicht - wird ignoriert?
		$rows = $datamodel->getCatData(array($catid));
		
		return;
		
		if(empty($rows)) {
			return;
		}
		
		$rows = $rows['rows'];
		
		$xmap->changeLevel(1);
		
		foreach($rows as $row) {
			$node = new stdclass;
			$node->id = $parent->id;
			$node->name = $row->title();
			$node->uid = $parent->uid . '_' . $row->id;
			$node->browserNav = $parent->browserNav;
			$node->priority = $params['image_priority'];
			$node->changefreq = $params['image_changefreq'];
			$node->link = $row->viewDetailLink($row->yup(), $row->mup(), $row->dup(), false);
				
			$xmap->printNode($node);
		}
		
		$xmap->changeLevel(-1);
	}
}