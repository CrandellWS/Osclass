<?php if ( !defined('ABS_PATH') ) exit('ABS_PATH is not loaded. Direct access is not allowed.') ;

    /*
     *      OSCLass – software for creating and publishing online classified
     *                           advertising platforms
     *
     *                        Copyright (C) 2010 OSCLASS
     *
     *       This program is free software: you can redistribute it and/or
     *     modify it under the terms of the GNU Affero General Public License
     *     as published by the Free Software Foundation, either version 3 of
     *            the License, or (at your option) any later version.
     *
     *     This program is distributed in the hope that it will be useful, but
     *         WITHOUT ANY WARRANTY; without even the implied warranty of
     *        MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
     *             GNU Affero General Public License for more details.
     *
     *      You should have received a copy of the GNU Affero General Public
     * License along with this program.  If not, see <http://www.gnu.org/licenses/>.
     */

    /**
     * 
     */
    class Search extends DAO
    {
        /**
         *
         * @var type 
         */
        private $conditions;
        private $itemConditions;
        private $tables;
        private $tables_join; // ?
        private $sql;
        private $order_column;
        private $order_direction;
        private $limit_init;
        private $results_per_page;
        private $cities;
        private $city_areas;
        private $regions;
        private $countries;
        private $categories;
        private $search_fields;
        private $total_results;
        private $total_results_table;
        private $sPattern;
        
        private $withPattern;
        private $withPicture;
        private $withLocations;
        private $withCategoryId;
        private $withUserId;
        private $withItemId;
        
        private $price_min;
        private $price_max;
        
        private $user_ids;
        private $itemId;
        
        private static $instance ;

        public static function newInstance()
        {
            if( !self::$instance instanceof self ) {
                self::$instance = new self ;
            }
            return self::$instance ;
        }

        /**
         * 
         */
        function __construct($expired = false) 
        {
            
            parent::__construct() ;
            $this->setTableName('t_item') ;
            $this->setFields( array('pk_i_id') ) ;
            
            $this->withPattern      = false;
            $this->withLocations    = false;
            $this->withCategoryId   = false;
            $this->withUserId       = false;
            $this->withPicture      = false;
            
            $this->price_min = null;
            $this->price_max = null;
            
            $this->user_ids  = null;
            $this->itemId    = null;
            
            $this->city_areas   = array();
            $this->cities       = array();
            $this->regions      = array();
            $this->countries    = array();
            $this->categories   = array();
            $this->conditions   = array();
            $this->tables       = array();
            $this->tables_join  = array();
            $this->search_fields = array();
            $this->itemConditions   = array();
            
            $this->order();
            $this->limit();
            $this->results_per_page = 10;
            
            if(!$expired) {
                // t_item 
                $this->addItemConditions(sprintf("%st_item.b_enabled = 1 ", DB_TABLE_PREFIX));
                $this->addItemConditions(sprintf("%st_item.b_active = 1 ", DB_TABLE_PREFIX));
                $this->addItemConditions(sprintf("%st_item.b_spam = 0", DB_TABLE_PREFIX));
                $this->addItemConditions(sprintf("(%st_item.b_premium = 1 || %st_item.dt_expiration >= '%s')", DB_TABLE_PREFIX, DB_TABLE_PREFIX, date('Y-m-d H:i:s')) ) ;
            }
            $this->total_results        = null;
            $this->total_results_table  = null;
            
            // get all item_location data
            if(OC_ADMIN) {
                $this->addField(sprintf('%st_item_location.*', DB_TABLE_PREFIX) );
            }
        }

        /**
         * Return an array with columns allowed for sorting
         * 
         * @return array
         */
        public static function getAllowedColumnsForSorting() 
        {
            return( array('i_price', 'dt_pub_date') ) ;
        }
        
        /**
         * Return an array with type of sorting
         * 
         * @return array
         */
        public static function getAllowedTypesForSorting() 
        {
            return ( array (0 => 'asc', 1 => 'desc') ) ;
        }
        

        // juanramon: little hack to get alerts work in search layout
        public function reconnect()
        {
         //   $this->conn = getConnection();
        }

        /**
         * Add conditions to the search
         *
         * @access public
         * @since unknown
         * @param mixed $conditions
         */
        public function addConditions($conditions) 
        {
            if(is_array($conditions)) {
                foreach($conditions as $condition) {
                    $condition = trim($condition);
                    if($condition!='') {
                        if(!in_array($condition, $this->conditions)) {
                            $this->conditions[] = $condition;
                        }
                    }
                }
            } else {
                $conditions = trim($conditions);
                if($conditions!='') {
                    if(!in_array($conditions, $this->conditions)) {
                        $this->conditions[] = $conditions;
                    }
                }
            }
        }
        
        /**
         * Add item conditions to the search
         *
         * @access public
         * @since unknown
         * @param mixed $conditions
         */
        public function addItemConditions($conditions) 
        {
            if(is_array($conditions)) {
                foreach($conditions as $condition) {
                    $condition = trim($condition);
                    if($condition!='') {
                        if(!in_array($condition, $this->itemConditions)) {
                            $this->itemConditions[] = $condition;
                        }
                    }
                }
            } else {
                $conditions = trim($conditions);
                if($conditions!='') {
                    if(!in_array($conditions, $this->itemConditions)) {
                        $this->itemConditions[] = $conditions;
                    }
                }
            }
        }

        /**
         * Add new fields to the search
         *
         * @access public
         * @since unknown
         * @param mixed $fields
         */
        public function addField($fields) 
        {
            if(is_array($fields)) {
                foreach($fields as $field) {
                    $field = trim($field);
                    if($field!='') {
                        if(!in_array($field, $this->fields)) {
                            $this->search_fields[] = $field;
                        }
                    }
                }
            }
            else {
                $fields = trim($fields);
                if($fields!='') {
                    if(!in_array($fields, $this->fields)) {
                        $this->search_fields[] = $fields;
                    }
                }
            }
        }

        /**
         * Add extra table to the search
         *
         * @access public
         * @since unknown
         * @param mixed $tables
         */
        public function addTable($tables) 
        {
            if(is_array($tables)) {
                foreach($tables as $table) {
                    $table = trim($table);
                    if($table!='') {
                        if(!in_array($table, $this->tables)) {
                            $this->tables[] = $table;
                        }
                    }
                }
            } else {
                $tables = trim($tables);
                if($tables!='') {
                    if(!in_array($tables, $this->tables)) {
                        $this->tables[] = $tables;
                    }
                }
            }
        }

        /**
         * Establish the order of the search
         *
         * @access public
         * @since unknown
         * @param string $o_c column
         * @param string $o_d direction
         * @param string $table
         */
        public function order($o_c = 'dt_pub_date', $o_d = 'DESC', $table = NULL) 
        {   
            if($table == '') {
                $this->order_column = $o_c;
            } else if($table != ''){
                if( $table == '%st_user' ) {
                    $this->order_column = sprintf("ISNULL($table.$o_c), $table.$o_c", DB_TABLE_PREFIX, DB_TABLE_PREFIX);
                } else {
                    $this->order_column = sprintf("$table.$o_c", DB_TABLE_PREFIX);
                }
            } else {
                $this->order_column = sprintf("$o_c", DB_TABLE_PREFIX);
            }
            $this->order_direction = $o_d;
        }

        /**
         * Limit the results of the search
         *
         * @access public
         * @since unknown
         * @param int $l_i
         * @param int $t_p_p results per page
         */
        public function limit($l_i = 0, $r_p_p = 10) 
        {
            $this->limit_init = $l_i;
            $this->results_per_page = $r_p_p;
        }

        /**
         * Limit the results of the search
         *
         * @access public
         * @since unknown
         * @param int $t_p_p results per page
         */
        public function set_rpp($r_p_p) 
        {
            $this->results_per_page = $r_p_p;
        }

        /**
         * Select the page of the search
         *
         * @access public
         * @since unknown
         * @param int $p page
         * @param int $t_p_p results per page
         */
        public function page($p = 0, $r_p_p = null) 
        {
            if($r_p_p!=null) { $this->results_per_page = $r_p_p; };
            $this->limit_init = $this->results_per_page*$p;
            $this->results_per_page = $this->results_per_page;
        }

        /**
         * Add city areas to the search
         *
         * @access public
         * @since unknown
         * @param mixed $city_area
         */
        public function addCityArea($city_area = array())
        {
            if(is_array($city_area)) {
                foreach($city_area as $c) {
                    $c = trim($c);
                    if($c!='') {
                        if(is_numeric($c)) {
                            $this->city_areas[] = sprintf("%st_item_location.fk_i_city_area_id = %d ", DB_TABLE_PREFIX, $c);
                        } else {
                            $_city_area = CityArea::newInstance()->findByName($c) ;
                            if( !empty($_city_area) ) {
                                $this->city_areas[] = sprintf("%st_item_location.fk_i_city_area_id = %d ", DB_TABLE_PREFIX, $_city_area['pk_i_id']);
                            } else {
                                $this->city_areas[] = sprintf("%st_item_location.s_city_area LIKE '%%%s%%' ", DB_TABLE_PREFIX, $c);
                            }
                        }
                    }
                }
            } else {
                $city_area = trim($city_area);
                if($city_area!="") {
                    if(is_numeric($city_area)) {
                        $this->city_areas[] = sprintf("%st_item_location.fk_i_city_area_id = %d ", DB_TABLE_PREFIX, $city_area);
                    } else {
                        echo "debug";
                        $_city_area = CityArea::newInstance()->findByName($city_area) ;
                        if( !empty($_city_area) ) {
                            $this->city_areas[] = sprintf("%st_item_location.fk_i_city_area_id = %d ", DB_TABLE_PREFIX, $_city_area['pk_i_id']);
                        } else {
                            $this->city_areas[] = sprintf("%st_item_location.s_city_area LIKE '%%%s%%' ", DB_TABLE_PREFIX, $city_area);
                        }
                    }
                }
            }
        }

        /**
         * Add cities to the search
         *
         * @access public
         * @since unknown
         * @param mixed $city
         */
        public function addCity($city = array()) 
        {
            if(is_array($city)) {
                foreach($city as $c) {
                    $c = trim($c);
                    if($c!='') {
                        if(is_numeric($c)) {
                            $this->cities[] = sprintf("%st_item_location.fk_i_city_id = %d ", DB_TABLE_PREFIX, $c);
                        } else {
                            $_city = City::newInstance()->findByName($c) ;
                            if( !empty($_city) ) {
                                $this->cities[] = sprintf("%st_item_location.fk_i_city_id = %d ", DB_TABLE_PREFIX, $_city);
                            } else {
                                $this->cities[] = sprintf("%st_item_location.s_city LIKE '%%%s%%' ", DB_TABLE_PREFIX, $c);
                            }
                        }
                    }
                }
            } else {
                $city = trim($city);
                if($city!="") {
                    if(is_numeric($city)) {
                        $this->cities[] = sprintf("%st_item_location.fk_i_city_id = %d ", DB_TABLE_PREFIX, $city);
                    } else {
                        $_city = City::newInstance()->findByName($city) ;
                        if( !empty($_city) ) {
                            $this->cities[] = sprintf("%st_item_location.fk_i_city_id = %d ", DB_TABLE_PREFIX, $_city['pk_i_id']);
                        } else {
                            $this->cities[] = sprintf("%st_item_location.s_city LIKE '%%%s%%' ", DB_TABLE_PREFIX, $city);
                        }
                    }
                }
            }
        }

        /**
         * Add regions to the search
         *
         * @access public
         * @since unknown
         * @param mixed $region
         */
        public function addRegion($region = array()) 
        {
            if(is_array($region)) {
                foreach($region as $r) {
                    $r = trim($r);
                    if($r!='') {
                        if(is_numeric($r)) {
                            $this->regions[] = sprintf("%st_item_location.fk_i_region_id = %d ", DB_TABLE_PREFIX, $r);
                        } else {
                            $_region = Region::newInstance()->findByName($r) ;
                            if( !empty($_region) ) {
                                $this->regions[] = sprintf("%st_item_location.fk_i_region_id = %d ", DB_TABLE_PREFIX, $_region['pk_i_id']);
                            } else {
                                $this->regions[] = sprintf("%st_item_location.s_region LIKE '%%%s%%' ", DB_TABLE_PREFIX, $r);
                            }
                        }
                    }
                }
            } else {
                $region = trim($region);
                if($region!="") {
                    if(is_numeric($region)) {
                        $this->regions[] = sprintf("%st_item_location.fk_i_region_id = %d ", DB_TABLE_PREFIX, $region);
                    } else {
                        $_region = Region::newInstance()->findByName($region) ;
                        if( !empty($_region) ) {
                            $this->regions[] = sprintf("%st_item_location.fk_i_region_id = %d ", DB_TABLE_PREFIX, $_region['pk_i_id']);
                        } else {
                            $this->regions[] = sprintf("%st_item_location.s_region LIKE '%%%s%%' ", DB_TABLE_PREFIX, $region);
                        }
                    }
                }
            }
        }

        /**
         * Add countries to the search
         *
         * @access public
         * @since unknown
         * @param mixed $country
         */
        public function addCountry($country = array()) 
        {
            if(is_array($country)) {
                foreach($country as $c) {
                    $c = trim($c);
                    if($c!='') {
                        if(strlen($c)==2) {
                            $this->countries[] = sprintf("%st_item_location.fk_c_country_code = '%s' ", DB_TABLE_PREFIX, strtolower($c));
                        } else {
                            $_country = Country::newInstance()->findByName($c) ;
                            if( !empty($_country) ) {
                                $this->countries[] = sprintf("%st_item_location.fk_c_country_code = '%s' ", DB_TABLE_PREFIX, strtolower($_country['pk_c_code']));
                            } else {
                                $this->countries[] = sprintf("%st_item_location.s_country LIKE '%%%s%%' ", DB_TABLE_PREFIX, $c);
                            }
                        }
                    }
                }
            } else {
                $country = trim($country);
                if($country!="") {
                    if(strlen($country)==2) {
                        $this->countries[] = sprintf("%st_item_location.fk_c_country_code = '%s' ", DB_TABLE_PREFIX, strtolower($country));
                    } else {
                        $_country = Country::newInstance()->findByName($c) ;
                        if( !empty($_country) ) {
                            $this->countries[] = sprintf("%st_item_location.fk_c_country_code = '%s' ", DB_TABLE_PREFIX, strtolower($_country['pk_c_code']));
                        } else {
                            $this->countries[] = sprintf("%st_item_location.s_country LIKE '%%%s%%' ", DB_TABLE_PREFIX, $country);
                        }
                    }
                }
            }
        }

        /**
         * Establish price range
         *
         * @access public
         * @since unknown
         * @param int $price_min
         * @param int $price_max
         */
        public function priceRange( $price_min = 0, $price_max = 0) 
        {
            $this->price_min = 1000000*$price_min;
            $this->price_max = 1000000*$price_max;
        }
        
        private function _priceRange()
        {
            if(is_numeric($this->price_min) && $this->price_min!=0) {
                $this->dao->where(sprintf("i_price >= %0.0f", $this->price_min));
            }
            if(is_numeric($this->price_max) && $this->price_max>0) {
                $this->dao->where(sprintf("i_price <= %0.0f", $this->price_max));
            }
        }

        /**
         * Establish max price
         *
         * @access public
         * @since unknown
         * @param int $price
         */
        public function priceMax($price) 
        {
            $this->priceRange(null, $price);
        }

        /**
         * Establish min price
         *
         * @access public
         * @since unknown
         * @param int $price
         */
        public function priceMin($price) 
        {
            $this->priceRange($price, null);
        }

        /**
         * Filter by ad with picture or not
         *
         * @access public
         * @since unknown
         * @param bool $pic
         */
        public function withPicture($pic = false) 
        {
            if($pic) {
                $this->withPicture = true;
            }
        }

        /**
         * Filter by search pattern 
         *
         * @access public
         * @since 2.4
         * @param string $pattern 
         */
        public function addPattern($pattern)
        {
            $this->withPattern  = true;
            $this->sPattern     = $pattern;
        }
        
        /**
         * Return ads from specified users
         *
         * @access public
         * @since unknown
         * @param mixed $id
         */
        public function fromUser($id = NULL) 
        {
            if(is_array($id)) {
                $this->withUserId = true;
                $ids = array();
                foreach($id as $_id) {
                    $ids[] = sprintf("%st_item.fk_i_user_id = %d ", DB_TABLE_PREFIX, $_id);
                }
                $this->user_ids = $ids;
            } else {
                $this->withUserId = true;
                $this->user_ids = $id;

            }
        }
        
        private function _fromUser()
        {
            $this->dao->from(sprintf('%st_user',DB_TABLE_PREFIX));
            $this->dao->where(sprintf('%st_user.pk_i_id = %st_item.fk_i_user_id',DB_TABLE_PREFIX,DB_TABLE_PREFIX));
            
            if(is_array($this->user_ids)) {
                $this->dao->where(" ( ".implode(" || ", $this->user_ids)." ) ");
            } else {
                $this->dao->where(sprintf("%st_item.fk_i_user_id = %d ", DB_TABLE_PREFIX, $this->user_ids));
            }
        }

        public function addItemId($id)
        {
            $this->withItemId = true;
            $this->itemId = $id;
        }
        /**
         * Clear the categories
         *
         * @access private
         * @since unknown
         * @param array $branches
         */
        private function pruneBranches($branches = null) 
        {
            if($branches!=null) {
                foreach($branches as $branch) {
                    if(!in_array($branch['pk_i_id'], $this->categories)) {
                        $this->categories[] = $branch['pk_i_id'] ; 
                        if(isset($branch['categories'])) {
                            $list = $this->pruneBranches($branch['categories']);
                        }
                    }
                }
            }
        }

        /**
         * Add categories to the search
         *
         * @access public
         * @since unknown
         * @param mixed $category
         */
        public function addCategory($category = null)
        {
            if( $category == null ) {
                return '' ;
            }

            if( !is_numeric($category) ) {
                $category  = preg_replace('|/$|','',$category);
                $aCategory = explode('/', $category) ;
                $category  = Category::newInstance()->findBySlug($aCategory[count($aCategory)-1]) ;

                if( count($category) == 0 ) {
                    return '' ;
                }

                $category  = $category['pk_i_id'] ;
            }

            $tree = Category::newInstance()->toSubTree($category) ;
            if( !in_array($category, $this->categories) ) {
                $this->categories[] = $category ;
            }
            $this->pruneBranches($tree);
        }
        
        /**
         *  Add joins for future use
         * 
         * @since 2.4
         * @param string $key
         * @param string $table
         * @param string $condition
         * @param string $type 
         */
        public function addJoinTable($key, $table, $condition, $type)
        {
            $this->tables_join[$key] = array($table, $condition, $type) ;
        }
        
        /**
         * Add join to current query
         * 
         * @since 2.4
         */
        private function _joinTable()
        {
            foreach($this->tables_join as $tJoin) {
                $this->dao->join( $tJoin[0], $tJoin[1], $tJoin[2] );
            }
        }
        
        /**
         * Create extraFields & conditionsSQL and return as an array
         *
         * @return array with extraFields & conditions strings
         */
        private function _conditions()
        {
            if(count($this->city_areas)>0) {
                $this->withLocations = true;
            }

            if(count($this->cities)>0) {
                $this->withLocations = true;
            }

            if(count($this->regions)>0) {
                $this->withLocations = true;
            }

            if(count($this->countries)>0) {
                $this->withLocations = true;
            }

            if(count($this->categories)>0) {
                $this->withCategoryId = true;
            }

            $conditionsSQL = implode(' AND ', $this->conditions);
            if($conditionsSQL!='') {
                $conditionsSQL = " ".$conditionsSQL;
            }

            $extraFields = "";
            if( count($this->search_fields) > 0 ) {
                $extraFields = ",";
                $extraFields .= implode(' ,', $this->search_fields);
            }
            
            return array(
                'extraFields'    => $extraFields,
                'conditionsSQL'  => $conditionsSQL
                );
        }
    
        /**
         * Only search by pattern + location + category
         *
         * @param type $num 
         */
        private function _makeSQLPremium($num = 2) 
        {
            if ($this->withPattern ) {
                // sub select for JOIN ----------------------
                $this->dao->select('distinct d.fk_i_item_id');
                $this->dao->from(DB_TABLE_PREFIX . 't_item_description as d');
                $this->dao->where(sprintf("MATCH(d.s_title, d.s_description) AGAINST('%s' IN BOOLEAN MODE)", $this->sPattern));

                $subSelect = $this->dao->_getSelect();
                $this->dao->_resetSelect();
                // END sub select ----------------------
                $this->dao->select(DB_TABLE_PREFIX.'t_item.*, '.DB_TABLE_PREFIX.'t_item.s_contact_name as s_user_name');
                $this->dao->from( DB_TABLE_PREFIX.'t_item' ) ;
                $this->dao->from(sprintf('%st_item_stats', DB_TABLE_PREFIX));
                $this->dao->where(sprintf('%st_item_stats.fk_i_item_id = %st_item.pk_i_id', DB_TABLE_PREFIX, DB_TABLE_PREFIX));
                $this->dao->where(sprintf("%st_item.b_premium = 1", DB_TABLE_PREFIX));
                
                if($this->withLocations || OC_ADMIN) {
                    $this->dao->join(sprintf('%st_item_location', DB_TABLE_PREFIX), sprintf('%st_item_location.fk_i_item_id = %st_item.pk_i_id', DB_TABLE_PREFIX, DB_TABLE_PREFIX), 'LEFT');
                    $this->_addLocations();
                }
                if($this->withCategoryId) {
                    $this->dao->where(sprintf("%st_item.fk_i_category_id", DB_TABLE_PREFIX) .' IN ('. implode(', ', $this->categories) .')' ) ;
                }
                $this->dao->where(DB_TABLE_PREFIX.'t_item.pk_i_id IN ('.$subSelect.')');
                
                $this->dao->groupBy(DB_TABLE_PREFIX.'t_item.pk_i_id');
                $this->dao->orderBy(sprintf('%st_item_stats.i_num_premium_views', DB_TABLE_PREFIX), 'ASC');
                $this->dao->orderBy(null, 'random');
                $this->dao->limit(0, $num);
                
            } else {
                $this->dao->select(DB_TABLE_PREFIX.'t_item.*, '.DB_TABLE_PREFIX.'t_item.s_contact_name as s_user_name');
                $this->dao->from( DB_TABLE_PREFIX.'t_item' ) ;
                $this->dao->from(sprintf('%st_item_stats', DB_TABLE_PREFIX));
                $this->dao->where(sprintf('%st_item_stats.fk_i_item_id = %st_item.pk_i_id', DB_TABLE_PREFIX, DB_TABLE_PREFIX));
                $this->dao->where(sprintf("%st_item.b_premium = 1", DB_TABLE_PREFIX));
                
                if($this->withLocations || OC_ADMIN) {
                    $this->dao->join(sprintf('%st_item_location', DB_TABLE_PREFIX), sprintf('%st_item_location.fk_i_item_id = %st_item.pk_i_id', DB_TABLE_PREFIX, DB_TABLE_PREFIX), 'LEFT');
                    $this->_addLocations();
                }
                if($this->withCategoryId) {
                    $this->dao->where(sprintf("%st_item.fk_i_category_id", DB_TABLE_PREFIX) .' IN ('. implode(', ', $this->categories) .')' ) ;
                }
                
                $this->dao->groupBy(DB_TABLE_PREFIX.'t_item.pk_i_id');
                $this->dao->orderBy(sprintf('%st_item_stats.i_num_premium_views', DB_TABLE_PREFIX), 'ASC');
                $this->dao->orderBy(null, 'random');
                $this->dao->limit(0, $num);
            }
            
            $sql = $this->dao->_getSelect() ;
            // reset dao attributes
            $this->dao->_resetSelect() ;
            
            return $sql;
        }
        
        private function _addLocations()
        {
            if(count($this->city_areas)>0) {
                $this->dao->where("( ".implode(' || ', $this->city_areas)." )");
            }
            if(count($this->cities)>0) {
                $this->dao->where("( ".implode(' || ', $this->cities)." )");
            }
            if(count($this->regions)>0) {
                $this->dao->where("( ".implode(' || ', $this->regions)." )");
            }
            if(count($this->countries)>0) {
                $this->dao->where("( ".implode(' || ', $this->countries)." )");
            }
        }
        /**
         * Make the SQL for the search with all the conditions and filters specified
         *
         * @access private
         * @since unknown
         * @param bool $count
         */
        private function _makeSQL($count = false,$premium = false) 
        {
            $arrayConditions    = $this->_conditions();
            $extraFields        = $arrayConditions['extraFields'];
            $conditionsSQL      = $arrayConditions['conditionsSQL'];
            
            $sql = '';
            
            if($this->withItemId) { 
                // add field s_user_name
                $this->dao->select(sprintf('%st_item.*', DB_TABLE_PREFIX) );
                $this->dao->from(sprintf('%st_item', DB_TABLE_PREFIX));
                $this->dao->where('pk_i_id', (int)$this->itemId);
            } else {  
                if($count) {
                    $this->dao->select(DB_TABLE_PREFIX.'t_item.pk_i_id');
                } else {
                    $this->dao->select(DB_TABLE_PREFIX.'t_item.*, '.DB_TABLE_PREFIX.'t_item.s_contact_name as s_user_name');
                    $this->dao->select($extraFields) ; // plugins!
                }
                $this->dao->from(DB_TABLE_PREFIX.'t_item');
                
                
                
                if ($this->withPattern ) {
                    $this->dao->join(DB_TABLE_PREFIX.'t_item_description as d','d.fk_i_item_id = '.DB_TABLE_PREFIX.'t_item.pk_i_id','LEFT');
                    $this->dao->where(sprintf("MATCH(d.s_title, d.s_description) AGAINST('%s' IN BOOLEAN MODE)", $this->sPattern) );
                }
                
                
                
                // item conditions 
                if(count($this->itemConditions)>0) {
                    $itemConditions = implode(' AND ', $this->itemConditions);
                    $this->dao->where($itemConditions);
                }
                if($this->withCategoryId) {
                    $this->dao->where(sprintf("%st_item.fk_i_category_id", DB_TABLE_PREFIX) .' IN ('. implode(', ', $this->categories) .')' ) ;
                }
                if($this->withUserId) {
                    $this->_fromUser();
                }
                if($this->withLocations || OC_ADMIN) {
                    $this->dao->join(sprintf('%st_item_location', DB_TABLE_PREFIX), sprintf('%st_item_location.fk_i_item_id = %st_item.pk_i_id', DB_TABLE_PREFIX, DB_TABLE_PREFIX), 'LEFT');
                    $this->_addLocations();
                }
                if($this->withPicture) {
                    $this->dao->join(sprintf('%st_item_resource', DB_TABLE_PREFIX), sprintf('%st_item_resource.fk_i_item_id = %st_item.pk_i_id', DB_TABLE_PREFIX, DB_TABLE_PREFIX), 'LEFT');
                    $this->dao->where(sprintf("%st_item_resource.s_content_type LIKE '%%image%%' ", DB_TABLE_PREFIX, DB_TABLE_PREFIX, DB_TABLE_PREFIX));
                    $this->dao->groupBy(DB_TABLE_PREFIX.'t_item.pk_i_id');
                }
                $this->_priceRange();

//                if ($this->withPattern ) {
//                    $this->dao->where(DB_TABLE_PREFIX.'t_item.pk_i_id IN ('.$subSelect.')');
//                }
                
                // PLUGINS TABLES !!
                if( !empty($this->tables) ) {
                    $tables = implode(', ', $this->tables) ;
                    $this->dao->from($tables) ;
                }           
                // WHERE PLUGINS extra conditions
                if(count($this->conditions) > 0) {
                    $this->dao->where($conditionsSQL) ;
                } 
                // ---------------------------------------------------------
                
                
                // order & limit
                $this->dao->orderBy( $this->order_column, $this->order_direction);

                if($count) {
                    $this->dao->limit(100*$this->results_per_page) ;
                } else {
                    $this->dao->limit( $this->limit_init, $this->results_per_page);
                }
            }
            
            $this->sql = $this->dao->_getSelect() ;
            // reset dao attributes
            $this->dao->_resetSelect() ;
            
            return $this->sql;
        }

        /**
         * Return number of ads selected
         *
         * @access public
         * @since unknown
         */
        public function count()
        {
            if( is_null($this->total_results) ) {
                $this->doSearch();
            }
            return $this->total_results;
        }
        
        /**
         * Return total items on t_item without any filter
         *
         * @return type 
         */
        public function countAll()
        {
            if( is_null($this->total_results_table) ) {
                $result = $this->dao->query(sprintf('select count(*) as total from %st_item', DB_TABLE_PREFIX ));
                $row = $result->row();
                $this->total_results_table = $row['total'];
            }
            return $this->total_results_table;
        }

        /**
         * Perform the search
         *
         * @access public
         * @since unknown
         * @param bool $extended if you want to extend ad's data
         */
        public function doSearch($extended = true, $count = true) 
        {   
            $sql = $this->_makeSQL(false) ;
            $result = $this->dao->query($sql);
            
            if($count) {
                $sql = $this->_makeSQL(true) ;
                $datatmp  = $this->dao->query( $sql ) ;
                $this->total_results = $datatmp->numRows() ;
            } else {
                $this->total_results = 0;                
            }
            
            if( $result == false ) {
                return array() ;
            }

            if($result) {
                $items = $result->result();
            } else { 
                $items = array();
            }

            if($extended) {
                return Item::newInstance()->extendData($items);
            } else {
                return $items;
            }
        }

        /**
         * Return premium ads related to the search
         *
         * @access public
         * @since unknown
         * @param int $max
         */
        /**
         * solo acepta pattern + location + stats, category
         * 
         */
        public function getPremiums($max = 2)
        {
            $premium_sql = $this->_makeSQLPremium($max); // make premium sql

            $result = $this->dao->query($premium_sql);
            $items = $result->result();
            
            $mStat = ItemStats::newInstance();
            foreach($items as $item) {
                $mStat->increase('i_num_premium_views', $item['pk_i_id']);
            }
            return Item::newInstance()->extendData($items);
        }
        
        /**
         * Return latest posted items, you can filter by category and specify the
         * number of items returned.
         * 
         * @param int $numItems
         * @param mixed $category int or array(int)
         * @param bool $withPicture
         * @return array
         */
        public function getLatestItems($numItems = 10, $category = array(), $withPicture = false)
        {            
            $this->dao->select(DB_TABLE_PREFIX.'t_item.*  '); // , '.DB_TABLE_PREFIX.'t_item_location.*  , cd.s_name as s_category_name') ;
            // from + tables
            $this->dao->from( DB_TABLE_PREFIX.'t_item use index (PRIMARY)' ) ;
            /*$this->dao->join( DB_TABLE_PREFIX.'t_item_description',
                    DB_TABLE_PREFIX.'t_item_description.fk_i_item_id = '.DB_TABLE_PREFIX.'t_item.pk_i_id',
                    'LEFT' ) ;
            $this->dao->join( DB_TABLE_PREFIX.'t_item_location', 
                    DB_TABLE_PREFIX.'t_item_location.fk_i_item_id = '.DB_TABLE_PREFIX.'t_item.pk_i_id',
                    'LEFT' ) ;
            $this->dao->join( DB_TABLE_PREFIX.'t_category',
                    DB_TABLE_PREFIX.'t_category.pk_i_id = '.DB_TABLE_PREFIX.'t_item.fk_i_category_id',
                    'LEFT' ) ;
            $this->dao->join( DB_TABLE_PREFIX.'t_category_description as cd',
                    DB_TABLE_PREFIX.'t_item.fk_i_category_id = cd.fk_i_category_id',
                    'LEFT' ) ;
            */
            if($withPicture) {
                $this->dao->from(sprintf('%st_item_resource', DB_TABLE_PREFIX));
                $this->dao->where(sprintf("%st_item_resource.s_content_type LIKE '%%image%%' AND %st_item.pk_i_id = %st_item_resource.fk_i_item_id", DB_TABLE_PREFIX, DB_TABLE_PREFIX, DB_TABLE_PREFIX));
            }
            
            // where
            $whe  = DB_TABLE_PREFIX.'t_item.b_active = 1 AND ';
            $whe .= DB_TABLE_PREFIX.'t_item.b_enabled = 1 AND ';
            $whe .= DB_TABLE_PREFIX.'t_item.b_spam = 0 AND ';
            
            $whe .= '('.DB_TABLE_PREFIX.'t_item.b_premium = 1 || '.DB_TABLE_PREFIX.'t_item.dt_expiration >= \''. date('Y-m-d H:i:s').'\') ';

            //$whe .= 'AND '.DB_TABLE_PREFIX.'t_category.b_enabled = 1 ';
            if( is_array($category) && !empty ($category) ) {
                $listCategories = implode(',', $category );
                $whe .= ' AND '.DB_TABLE_PREFIX.'t_item.fk_i_category_id IN ('.$listCategories.') ';
            }
            $this->dao->where( $whe );
            
            // group by & order & limit
            $this->dao->groupBy(DB_TABLE_PREFIX.'t_item.pk_i_id');
            $this->dao->orderBy(DB_TABLE_PREFIX.'t_item.pk_i_id', 'DESC');
            $this->dao->limit(0, $numItems);
            
            $rs = $this->dao->get();
            
            if($rs === false){
                return array();
            }
            if( $rs->numRows() == 0 ) {
                return array();
            }

            $items = $rs->result();
            return Item::newInstance()->extendData($items);
        }

        /**
         * Returns number of ads from each country
         *
         * @deprecated
         * @access public
         * @since unknown
         * @param string $zero if you want to include locations with zero results
         * @param string $order
         */
        public function listCountries($zero = ">", $order = "items DESC")
        {
           return CountryStats::newInstance()->listCities($zero, $order);
        }

        /**
         * Returns number of ads from each region
         * <code>
         *  Search::newInstance()->listRegions($country, ">=", "country_name ASC" )
         * </code>
         * 
         * @deprecated
         * @access public
         * @since unknown
         * @param string $country
         * @param string $zero if you want to include locations with zero results
         * @param string $order
         */
        public function listRegions($country = '%%%%', $zero = ">", $order = "items DESC") 
        {    
           return RegionStats::newInstance()->listRegions($country, $zero, $order);
        }

        /**
         * Returns number of ads from each city
         *
         * <code>
         *  Search::newInstance()->listCities($region, ">=", "city_name ASC" )
         * </code>
         * 
         * @deprecated
         * @access public
         * @since unknown
         * @param string $region
         * @param string $zero if you want to include locations with zero results
         * @param string $order
         */
        public function listCities($region = null, $zero = ">", $order = "city_name ASC") 
        {
            return CityStats::newInstance()->listCities($region, $zero, $order);
        }

        /**
         * Returns number of ads from each city area
         *
         * @access public
         * @since unknown
         * @param string $city
         * @param string $zero if you want to include locations with zero results
         * @param string $order
         */
        public function listCityAreas($city = null, $zero = ">", $order = "items DESC") 
        {
           $aOrder = split(' ', $order);
            $nOrder = count($aOrder);
            
            if($nOrder == 2) $this->dao->orderBy($aOrder[0], $aOrder[1]);
            else if($nOrder == 1) $this->dao->orderBy($aOrder[0], 'DESC');
            else $this->dao->orderBy('item', 'DESC');
            
            $this->dao->select('fk_i_city_area_id as city_area_id, s_city_area as city_area_name, fk_i_city_id , s_city as city_name, fk_i_region_id as region_id, s_region as region_name, fk_c_country_code as pk_c_code, s_country as country_name, count(*) as items, '.DB_TABLE_PREFIX.'t_country.fk_c_locale_code');
            $this->dao->from(DB_TABLE_PREFIX.'t_item, '.DB_TABLE_PREFIX.'t_item_location, '.DB_TABLE_PREFIX.'t_category, '.DB_TABLE_PREFIX.'t_country');
            $this->dao->where(DB_TABLE_PREFIX.'t_item.pk_i_id = '.DB_TABLE_PREFIX.'t_item_location.fk_i_item_id');
            $this->dao->where(DB_TABLE_PREFIX.'t_item.b_enabled = 1');
            $this->dao->where(DB_TABLE_PREFIX.'t_item.b_active = 1');
            $this->dao->where(DB_TABLE_PREFIX.'t_item.b_spam = 0');
            $this->dao->where(DB_TABLE_PREFIX.'t_category.b_enabled = 1');
            $this->dao->where(DB_TABLE_PREFIX.'t_category.pk_i_id = '.DB_TABLE_PREFIX.'t_item.fk_i_category_id');
            $this->dao->where('('.DB_TABLE_PREFIX.'t_item.b_premium = 1 || '.DB_TABLE_PREFIX.'t_category.i_expiration_days = 0 || DATEDIFF(\''.date('Y-m-d H:i:s').'\','.DB_TABLE_PREFIX.'t_item.dt_pub_date) < '.DB_TABLE_PREFIX.'t_category.i_expiration_days)');
            $this->dao->where('fk_i_city_area_id IS NOT NULL');
            $this->dao->where(DB_TABLE_PREFIX.'t_country.pk_c_code = fk_c_country_code');
            $this->dao->groupBy('fk_i_city_area_id');
            $this->dao->having("items $zero 0");
            
            $city_int = (int)$city;

            if(is_numeric($city_int) && $city_int!=0) {
                $this->dao->where("fk_i_city_id = $city_int");
            }
            
            $result = $this->dao->get();
            return $result->result();
        }
        
        /**
         * Given the current search object, extract search parameters & conditions
         * as array.
         * 
         * @return array
         */
        private function _getConditions() 
        {
            $aData = array();
   
            $item_id                = DB_TABLE_PREFIX.'t_item.pk_i_id';
            $item_category_id       = DB_TABLE_PREFIX.'t_item.fk_i_category_id';
            $item_description_id    = 'd.fk_i_item_id';
            $category_id            = DB_TABLE_PREFIX.'t_category.pk_i_id';
            $item_location_id       = DB_TABLE_PREFIX.'t_item_location.fk_i_item_id';
            $item_resource_id       = DB_TABLE_PREFIX.'t_item_resource.fk_i_item_id';
            
            // get item conditions
            foreach($this->conditions as $condition) {
                // item table
                if(preg_match('/'.DB_TABLE_PREFIX.'t_item\.b_active/', $condition, $matches) ) {
                    $aData['itemConditions'][] = $condition;
                } else if(preg_match('/'.DB_TABLE_PREFIX.'t_item\.b_spam/', $condition, $matches) ) {
                    $aData['itemConditions'][] = $condition;
                } else if(preg_match('/'.DB_TABLE_PREFIX.'t_item\.b_enabled/', $condition, $matches) ) {
                    $aData['itemConditions'][] = $condition;
                } else if(preg_match('/'.DB_TABLE_PREFIX.'t_item\.b_premium/', $condition, $matches) ) {
                    $aData['itemConditions'][] = $condition;
                } else if(preg_match('/('.DB_TABLE_PREFIX.'t_item\.)?f_price >= (.*)/', $condition, $matches) ) {
                    $aData['price_min'] = (int) $matches[2];
                } else if(preg_match('/('.DB_TABLE_PREFIX.'t_item\.)?f_price <= (.*)/', $condition, $matches) ) {
                    $aData['price_max'] = (int) $matches[2];
                } else if(preg_match('/('.DB_TABLE_PREFIX.'t_item\.)?i_price >= (.*)/', $condition, $matches) ) {
                    $aData['price_min'] = ( (double) $matches[2] / 1000000 );
                } else if(preg_match('/('.DB_TABLE_PREFIX.'t_item\.)?i_price <= (.*)/', $condition, $matches) ) {
                    $aData['price_max'] = ( (double) $matches[2] / 1000000 );
                } else if(preg_match('/'.DB_TABLE_PREFIX.'t_category.b_enabled/', $condition, $matches) ) {
                    // t_category.b_enabled is not longer needed
                } else if(preg_match_all('/('.DB_TABLE_PREFIX.'t_item_location.s_city_area\s*LIKE\s*\'%([\s\p{L}\p{N}]*)%\'\s*)/u', $condition, $matches) ) { // OJO
                    // Comprobar: si ( s_name existe ) then get location id, 
                    $aData['s_city_area'][] = DB_TABLE_PREFIX.'t_item_location.s_city_area LIKE \'%'.$matches[2][0].'%\'';
                } else if(preg_match('/'.DB_TABLE_PREFIX.'t_item_location.fk_i_city_area_id = (.*)/', $condition, $matches) ) {
                    $aData['fk_i_city_area_id'][] = DB_TABLE_PREFIX.'t_item_location.fk_i_city_area_id = '.$matches[1];
                } else if(preg_match_all('/('.DB_TABLE_PREFIX.'t_item_location.s_city\s*LIKE\s*\'%([\s\p{L}\p{N}]*)%\'\s*)/u', $condition, $matches) ) { // OJO
                    // Comprobar: si ( s_name existe ) then get location id, 
                    $aData['cities'][] = DB_TABLE_PREFIX.'t_item_location.s_city LIKE \'%'.$matches[2][0].'%\'';
                } else if(preg_match('/'.DB_TABLE_PREFIX.'t_item_location.fk_i_city_id = (.*)/', $condition, $matches) ) {
                    $aData['cities'][] = DB_TABLE_PREFIX.'t_item_location.fk_i_city_id = '.$matches[1];
                } else if(preg_match_all('/('.DB_TABLE_PREFIX.'t_item_location.s_region\s*LIKE\s*\'%([\s\p{L}\p{N}]*)%\'\s*)/u', $condition, $matches) ) { // OJO
                    // Comprobar: si ( s_name existe ) then get location id, 
                    $aData['s_region'][] = DB_TABLE_PREFIX.'t_item_location.s_region LIKE \'%'.$matches[2][0].'%\'';
                } else if(preg_match('/'.DB_TABLE_PREFIX.'t_item_location.fk_i_region_id = (.*)/', $condition, $matches) ) {
                    $aData['fk_i_region_id'] = DB_TABLE_PREFIX.'t_item_location.fk_i_region_id = '.$matches[1];
                } else if(preg_match_all('/('.DB_TABLE_PREFIX.'t_item_location.s_country\s*LIKE\s*\'%([\s\p{L}\p{N}]*)%\'\s*)/u', $condition, $matches) ) { // OJO
                    // Comprobar: si ( s_name existe ) then get location id,  
                    $aData['s_country'][] = DB_TABLE_PREFIX.'t_item_location.s_country LIKE \'%'.$matches[2][0].'%\'';
                } else if(preg_match('/'.DB_TABLE_PREFIX.'t_item_location.fk_c_country_code = \'?(.*)\'?/', $condition, $matches) ) {
                    $aData['fk_c_country_code'][] = DB_TABLE_PREFIX.'t_item_location.fk_c_country_code = '.$matches[1];
                } else if(preg_match('/d\.s_title\s*LIKE\s*\'%([\s\p{L}\p{N}]*)%\'/u', $condition, $matches) ) {  // OJO
                    $aData['sPattern']      = $matches[1];
                    $aData['withPattern']   = true;
                } else if(preg_match('/MATCH\(d\.s_title, d\.s_description\) AGAINST\(\'([\s\p{L}\p{N}]*)\' IN BOOLEAN MODE\)/u', $condition, $matches) ) { // OJO
                    $aData['sPattern'] = $matches[1];
                    $aData['withPattern']   = true;
                } else if(preg_match("/$item_id\s*=\s*$item_description_id/", $condition, $matches_1)   || preg_match("/$item_description_id\s*=\s*$item_id/", $condition, $matches_2)) {
                } else if(preg_match("/$category_id\s*=\s*$item_category_id/", $condition, $matches_1)  || preg_match("/$item_id\s*=\s*$item_category_id/", $condition, $matches_2)) {
                } else if(preg_match("/$item_location_id\s*=\s*$item_id/", $condition, $matches_1)      || preg_match("/$item_id\s*=\s*$item_location_id/", $condition, $matches_2)) {
                } else if(preg_match("/$item_id\s*=\s*$item_resource_id/", $condition, $matches_1)      || preg_match("/$item_resource_id\s*=\s*$item_id/", $condition, $matches_2)) {
                    // nothing to do, catch table
                } else if(preg_match_all('/(oc_t_item\.fk_i_category_id = (\d*))/', $condition, $matches) ) {
                    $aData['aCategories'] = $matches[2];
                } else {
                    $aData['no_catched_conditions'][] = $condition;
                }
            }
            
            // get tables
            foreach($this->tables as $table) {
                if( preg_match('/('.DB_TABLE_PREFIX.'t_item$)/', $table, $matches ) ) {
                    // t_item is allways included
                } else if( preg_match('/('.DB_TABLE_PREFIX.'t_item_description( as d)?)/', $table, $matches ) ) {
                    // t_item_description is allways included
                } else if( preg_match('/'.DB_TABLE_PREFIX.'t_category/', $table, $matches ) ) {
                    // t_category is allways included
                } else if( preg_match('/('.DB_TABLE_PREFIX.'t_category_description( as cd)?)/', $table, $matches ) ) {
                    // t_item_description
                    $aData['tables'][] = $matches[1];
                } else if( preg_match('/('.DB_TABLE_PREFIX.'t_item_resource)/', $table, $matches ) ) {
                    $aData['withPicture'] = true;
                } else {
                    $aData['no_catched_tables'][] = $table;
                }
            }
            
            // get order & limit
            $aData['order_column']      = $this->order_column;
            $aData['order_direction']   = $this->order_direction;
            $aData['limit_init']        = $this->limit_init;
            $aData['results_per_page']  = $this->results_per_page;
            
            return $aData;
        }
        
        /**
         * Return json with all search attributes
         *
         * @return string
         */
        public function toJson($convert = false)
        {
            if($convert) {
                $aData = $this->_getConditions();
            } else {
                $aData['price_min']     = $this->price_min / 1000000;
                $aData['price_max']     = $this->price_max / 1000000;
                $aData['aCategories']   = $this->categories;
                // locations
                $aData['city_areas']    = $this->city_areas;
                $aData['cities']        = $this->cities;
                $aData['regions']       = $this->regions;
                $aData['countries']     = $this->countries;
                // pattern
                $aData['withPattern']   = $this->withPattern;
                $aData['sPattern']      = $this->sPattern;
                if($this->withPicture) {
                    $aData['withPicture']   = $this->withPicture;
                }
                
                $aData['tables']        = $this->tables;
                $aData['tables_join']   = $this->tables_join;
                
                $aData['no_catched_tables']     = $this->tables;
                $aData['no_catched_conditions'] = $this->conditions;
                
                $aData['user_ids']          = $this->user_ids;
                
                // get order & limit
                $aData['order_column']      = $this->order_column;
                $aData['order_direction']   = $this->order_direction;
                $aData['limit_init']        = $this->limit_init;
                $aData['results_per_page']  = $this->results_per_page;
            }
            return json_encode($aData);
        }
        
        public function setJsonAlert($aData)
        {
            $this->priceRange($aData['price_min'], $aData['price_max'] );

            $this->categories   = $aData['aCategories'];
            // locations
            $this->city_areas   = $aData['city_areas'] ;
            $this->cities       = $aData['cities']; 
            $this->regions      = $aData['regions'] ;
            $this->countries    = $aData['countries'];

            $this->user_ids     = $aData['user_ids'];

            $this->tables_join  = $aData['tables_join'];
            $this->tables       = $aData['no_catched_tables'];
            $this->conditions   = $aData['no_catched_conditions'];

            // get order & limit
            $this->order_column     = $aData['order_column'];
            $this->order_direction  = $aData['order_direction'];
            $this->limit_init       = $aData['limit_init'];
            $this->results_per_page = $aData['results_per_page'];

            // pattern
            if(isset($aData['sPattern']) ) {
                $this->addPattern($aData['sPattern']);
            }
            if( isset($aData['withPicture']) ) {
                $this->withPicture(true);
            }
        }
    }

    /* file end: ./oc-includes/osclass/model/Search.php */
?>
