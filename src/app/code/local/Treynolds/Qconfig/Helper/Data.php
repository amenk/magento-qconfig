<?php

class Treynolds_Qconfig_Helper_Data extends Mage_Core_Helper_Abstract {
    /**
     * @param $qsearch string
     * @param $sections Mage_Core_Model_Config_Element
     * @param $configRoot Varien_Simplexml_Element
     * @param $levelClause string
     * @return array
     */
    protected function getNavRecords($qsearch, $sections, $configRoot, $levelClause){
        $nav_ret = array();
        $nodes = array_merge(
            $sections->xpath('*[.//label[contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "'.$qsearch.'") and ../'.$levelClause.'="1"]]')
            ,$configRoot->xpath('*[./*/*[contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "'. $qsearch.'")]]')

        );

        /* @var $node Mage_Core_Model_Config_Element */
        foreach($nodes as $node){
            $nav_ret[] = 'section/'. $node->getName(0);
        }
        return $nav_ret;
    }

    /**
     * @param $qsearch string
     * @param $current string
     * @param $sections Mage_Core_Model_Config_Element
     * @param $levelClause string
     * @return array
     */
    protected function getGroupAndFieldRecordsByLabel($qsearch, $current, $sections, $levelClause){
        $group_ret = array();
        $field_ret = array();
        $nodes = $sections->xpath($current . '/groups//label[contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "'.$qsearch.'") and ../'.$levelClause.'="1"]');

        foreach($nodes as $node){
            $path = array();
            $parent = $node->xpath('.');
            $sanity = 0;
            while($parent[0]->getName()!=$current && $sanity++ < 10){
                $path[] = $parent[0]->getName();
                $parent = $parent[0]->xpath('./..');
            }
            $path[] = $current;
            /* The count is 4 when we matched a 'group' label */
            if(count($path)==4){
                $group_ret[] = $path[3]. '_' . $path[1] . '-head';
            }
            /* The count is 6 when we match a 'field' label */
            else if(count($path)==6) {
                $group_ret[] = $path[5]. '_' . $path[3];
                $field_ret[] ='row_' .  $path[5] . '_' . $path[3] . '_' . $path[1];
            }

        }

        return array($group_ret, $field_ret);
    }

    /**
     * @param $qsearch string
     * @param $current string
     * @param $configRoot Varien_Simplexml_Element
     * @return array
     */
    protected function getGroupAndFieldRecordsByValue($qsearch, $current, $configRoot){
        $group_ret = array();
        $field_ret = array();

        $nodes = $configRoot->xpath($current . '//*[contains(translate(text(), "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "'. $qsearch.'")]');
        foreach($nodes as $node){
            $path = array();

            $parent = $node->xpath('.');
            $sanity = 0;
            while($parent[0]->getName()!=$current && $sanity++ < 10){
                $path[] = $parent[0]->getName();
                $parent = $parent[0]->xpath('./..');
            }
            $path[] = $current;
            if(count($path)==3){
                $field_ret[] = 'row_' . $path[2] . '_' . $path[1] . '_' . $path[0];
                $group_ret[] = $path[2] . '_' . $path[1];
            }

        }


        return array($group_ret, $field_ret);
    }

    /**
     * @param $qsearch string Query String
     * @param $current string The current section of config you are viewing
     * @param $website string The current website you are under. Can be null or empty string
     * @param $store string The store view you are under. Can be null or empty string
     * @return array with keys (nav, group, field), each of which is an array of strings
     */
    public function getQuickSearchResults($qsearch, $current, $website, $store){
        if(is_null($current)){
            $current = 'general';//This is currently not needed. Parameter gets set in adminhtml/system_config_tabs:122
        }

        $qsearch =  trim(strtolower($qsearch));
        if(strlen($qsearch)==0){
            return array('nav'=>array(),'group'=>array(), 'field'=>array());
        }
        $qsearch = preg_replace('/("|\[|\]|\(|\))/','',$qsearch);
        $levelClause = $this->getLevelClause($website, $store);
        /* @var $configFields Mage_Adminhtml_Model_Config */
        $configFields = Mage::getSingleton('adminhtml/config');
        /* @var $formBlock Mage_Adminhtml_Block_System_Config_Form */
        $formBlock = Mage::app()->getLayout()->createBlock('adminhtml/system_config_form');
        /* @var $sections Varien_Simplexml_Element */
        $configRoot = $formBlock->getConfigRoot();
        /* @var $sections Mage_Core_Model_Config_Element */
        $sections = $configFields->getSections($current);
        /**
         * First, get the top-level nodes for the left-hand nav.
         */
        $nav_ret = $this->getNavRecords($qsearch, $sections, $configRoot, $levelClause);


        /**
         * For finding the elements on your page we have to do things a little different
         * We can't combine the xpath because we are grabbing the lowest level nodes
         * and since the xml structure of the Config differs from the structure of the
         * config display xml the parsing is slightly different.
         * Essentially, in the config display xml there is a max depth and there are
         * filler tags (groups, fields). In the actual config xml there aren't fillers
         * and the depth can be more variable.
         *
         * This results in an array with duplicates, but that doesn't have much effect
         * on the front-end.
         */
        /* Config display xml for the page you are on */
        $by_label = $this->getGroupAndFieldRecordsByLabel($qsearch, $current, $sections, $levelClause);
        /* Next we get the actual config xml for the page you are on */
        $by_value = $this->getGroupAndFieldRecordsByValue($qsearch, $current, $configRoot);
        /* Finally, we handle edge cases */
        //TODO: Figure out how to handle edge cases


        $group_ret = array_merge($by_value[0], $by_label[0]);
        $field_ret = array_merge($by_value[1], $by_label[1]);
        return array('nav'=>$nav_ret, 'group'=>$group_ret, 'field'=>$field_ret);
    }

    /**
     * @return array where the key is a string to match qsearch
     *         and the value is an array of xpath clauses
     */
    protected function getNavEdgeCases(){
        return array('yes'=>1, 'no'=>0, 'enabled'=>1, 'disabled'=>0);
    }

    /**
     * Need to check the "show_in_X" tags in system.xml files
     * @param $website string
     * @param $store string
     * @return string
     */
    protected function getLevelClause($website, $store){
        if(!is_null($store) && strlen($store)>0){
            return 'show_in_store';
        }
        if(!is_null($website) && strlen($website)>0){
            return 'show_in_website';
        }
        return 'show_in_default';
    }
}