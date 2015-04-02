<?php

/**
 * @author     Branko Wilhelm <branko.wilhelm@gmail.com>
 * @link       http://www.z-index.net
 * @copyright  (c) 2013 - 2015 Branko Wilhelm
 * @license    GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die;

class xmap_com_jevents
{
    /**
     * @var bool
     */
    private static $enabled = false;

    public function __construct()
    {
        self::$enabled = JComponentHelper::isEnabled('com_jevents');

        if (self::$enabled)
        {
            require_once JPATH_SITE . '/components/com_jevents/jevents.defines.php';
        }
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     *
     * @throws Exception
     */
    public static function getTree($xmap, stdClass $parent, array &$params)
    {
        $item = JFactory::getApplication()->getMenu()->getItem($parent->id);

        if (!self::$enabled || empty($item) || $item->query['view'] != 'cat')
        {
            return;
        }

        $params['groups'] = implode(',', JFactory::getUser()->getAuthorisedViewLevels());

        $params['include_events'] = JArrayHelper::getValue($params, 'include_events', 1);
        $params['include_events'] = ($params['include_events'] == 1 || ($params['include_events'] == 2 && $xmap->view == 'xml') || ($params['include_events'] == 3 && $xmap->view == 'html'));

        $params['show_unauth'] = JArrayHelper::getValue($params, 'show_unauth', 0);
        $params['show_unauth'] = ($params['show_unauth'] == 1 || ($params['show_unauth'] == 2 && $xmap->view == 'xml') || ($params['show_unauth'] == 3 && $xmap->view == 'html'));

        $params['category_priority'] = JArrayHelper::getValue($params, 'category_priority', $parent->priority);
        $params['category_changefreq'] = JArrayHelper::getValue($params, 'category_changefreq', $parent->changefreq);

        if ($params['category_priority'] == -1)
        {
            $params['category_priority'] = $parent->priority;
        }

        if ($params['category_changefreq'] == -1)
        {
            $params['category_changefreq'] = $parent->changefreq;
        }

        $params['event_priority'] = JArrayHelper::getValue($params, 'event_priority', $parent->priority);
        $params['event_changefreq'] = JArrayHelper::getValue($params, 'event_changefreq', $parent->changefreq);

        if ($params['event_priority'] == -1)
        {
            $params['event_priority'] = $parent->priority;
        }

        if ($params['event_changefreq'] == -1)
        {
            $params['event_changefreq'] = $parent->changefreq;
        }

        if (is_array($item->params->get('catidnew')))
        {
            self::getCategoryTree($xmap, $parent, $params, $item->params->get('catidnew'));
        } else
        {
            self::getCategoryTree($xmap, $parent, $params, array(1));
        }
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param array $catids
     */
    private static function getCategoryTree($xmap, $parent, array &$params, array $catids)
    {
        $db = JFactory::getDBO();

        $query = $db->getQuery(true)
            ->select(array('id', 'title', 'parent_id'))
            ->from('#__categories')
            ->where('extension = ' . $db->Quote('com_jevents'))
            ->where('parent_id IN(' . implode(',', $catids) . ')')
            ->order('lft');

        if (!$params['show_unauth'])
        {
            $query->where('access IN(' . $params['groups'] . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows))
        {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row)
        {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->title;
            $node->uid = $parent->uid . '_cid_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['category_priority'];
            $node->changefreq = $params['category_changefreq'];
            $node->pid = $row->parent_id;
            $node->link = 'index.php?option=com_jevents&task=cat.listevents&offset=1&category_fv=' . $row->id . '&Itemid=' . $parent->id;

            if ($xmap->printNode($node) !== false)
            {
                self::getCategoryTree($xmap, $parent, $params, array($row->id));
                if ($params['include_events'])
                {
                    self::getEvents($xmap, $parent, $params, $row->id);
                }
            }
        }

        $xmap->changeLevel(-1);
    }

    /**
     * @param XmapDisplayerInterface $xmap
     * @param stdClass $parent
     * @param array $params
     * @param $catid
     */
    private static function getEvents($xmap, $parent, array &$params, $catid)
    {
        static $datamodel;

        if (!$datamodel)
        {
            $datamodel = new JEventsDataModel;
        }

        $rows = $datamodel->queryModel->listIcalEventsByCat(array($catid));

        if (empty($rows))
        {
            return;
        }

        $xmap->changeLevel(1);

        foreach ($rows as $row)
        {
            $node = new stdclass;
            $node->id = $parent->id;
            $node->name = $row->title();
            $node->uid = $parent->uid . '_' . $row->id;
            $node->browserNav = $parent->browserNav;
            $node->priority = $params['event_priority'];
            $node->changefreq = $params['event_changefreq'];
            $node->link = $row->viewDetailLink($row->yup(), $row->mup(), $row->dup(), false);

            $xmap->printNode($node);
        }

        $xmap->changeLevel(-1);
    }
}