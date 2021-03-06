<?php

class modelContent extends cmsModel{

//============================================================================//
//===================    ПРЕФИКС ТАБЛИЦ КОНТЕНТА   ===========================//
//============================================================================//

    public $table_prefix = 'con_';

    protected $pub_filter_disabled = false;
    protected $pub_filtered = false;
    protected $approved_filter_disabled = false;
    protected $approved_filtered = false;

    private static $all_ctypes = null;

    public function __construct() {

        parent::__construct();

        $this->loadAllCtypes();

    }

    public function setTablePrefix($prefix){
        $this->table_prefix = $prefix;
        return $this;
    }

//============================================================================//
//=======================    ТИПЫ КОНТЕНТА   =================================//
//============================================================================//

    public function addContentType($ctype){

        $id = $this->insert('content_types', $ctype);

        $config = cmsConfig::getInstance();

        // получаем структуру таблиц для хранения контента данного типа
        $content_table_struct      = $this->getContentTableStruct();
        $fields_table_struct       = $this->getFieldsTableStruct();
        $props_table_struct        = $this->getPropsTableStruct();
        $props_bind_table_struct   = $this->getPropsBindTableStruct();
        $props_values_table_struct = $this->getPropsValuesTableStruct();

        // создаем таблицы
        $table_name = $this->table_prefix . $ctype['name'];

        $this->db->createTable($table_name, $content_table_struct);
        $this->db->createTable("{$table_name}_fields", $fields_table_struct, $config->db_engine);
        $this->db->createCategoriesTable("{$table_name}_cats");
		$this->db->createCategoriesBindsTable("{$table_name}_cats_bind");

        $this->db->createTable("{$table_name}_props", $props_table_struct, $config->db_engine);
        $this->db->createTable("{$table_name}_props_bind", $props_bind_table_struct, $config->db_engine);
        $this->db->createTable("{$table_name}_props_values", $props_values_table_struct, $config->db_engine);

        //
        // добавляем стандартные поля
        //

        // заголовок
        $this->addContentField($ctype['name'], array(
            'name' => 'title',
            'title' => LANG_TITLE,
            'type' => 'caption',
            'ctype_id' => $id,
            'is_in_list' => 1,
            'is_in_item' => 1,
            'is_in_filter' => 1,
            'is_fixed' => 1,
            'is_fixed_type' => 1,
            'is_system' => 0,
            'options' => array(
                'label_in_list' => 'none',
                'label_in_item' => 'none',
                'min_length' => 3,
                'max_length' => 100,
                'is_required' => true
            )
        ), true);

        // дата публикации
        $this->addContentField($ctype['name'], array(
            'name' => 'date_pub',
            'title' => LANG_DATE_PUB,
            'type' => 'date',
            'ctype_id' => $id,
            'is_in_list' => 1,
            'is_in_item' => 1,
            'is_in_filter' => 1,
            'is_fixed' => 1,
            'is_fixed_type' => 1,
            'is_system' => 1,
            'options' => array(
                'label_in_list' => 'none',
                'label_in_item' => 'left',
                'show_time' => true
            )
        ), true);

        // автор
        $this->addContentField($ctype['name'], array(
            'name' => 'user',
            'title' => LANG_AUTHOR,
            'type' => 'user',
            'ctype_id' => $id,
            'is_in_list' => 1,
            'is_in_item' => 1,
            'is_in_filter' => 0,
            'is_fixed' => 1,
            'is_fixed_type' => 1,
            'is_system' => 1,
            'options' => array(
                'label_in_list' => 'none',
                'label_in_item' => 'left'
            )
        ), true);

        // фотография
        $this->addContentField($ctype['name'], array(
            'name' => 'photo',
            'title' => LANG_PHOTO,
            'type' => 'image',
            'ctype_id' => $id,
            'is_in_list' => 1,
            'is_in_item' => 1,
            'is_fixed' => 1,
            'options' => array(
                'size_teaser' => 'small',
                'size_full' => 'normal',
                'sizes' => array('micro', 'small', 'normal', 'big')
            )
        ), true);

        // описание
        $this->addContentField($ctype['name'], array(
            'name' => 'content',
            'title' => LANG_DESCRIPTION,
            'type' => 'text',
            'ctype_id' => $id,
            'is_in_list' => 1,
            'is_in_item' => 1,
            'is_fixed' => 1,
            'options' => array(
                'label_in_list' => 'none',
                'label_in_item' => 'none'
            )
        ), true);

        cmsCache::getInstance()->clean("content.types");

        return $id;

    }

//============================================================================//
//============================================================================//

    public function updateContentType($id, $item){

        cmsCache::getInstance()->clean("content.types");

        return $this->update('content_types', $id, $item);

    }

//============================================================================//
//============================================================================//

    public function deleteContentType($id){

        $ctype = $this->getContentType($id);

        if ($ctype['is_fixed']) { return false; }

		$items = $this->getContentItems($ctype['name']);
		if ($items){
			foreach($items as $item){
				$this->deleteContentItem($ctype['name'], $item['id']);
			}
		}

		cmsCore::getModel('tags')->recountTagsFrequency();

        $this->delete('content_types', $id);
        $this->delete('content_datasets', $id, 'ctype_id');

        $table_name = $this->table_prefix . $ctype['name'];

        $this->db->dropTable("{$table_name}");
        $this->db->dropTable("{$table_name}_fields");
        $this->db->dropTable("{$table_name}_cats");
        $this->db->dropTable("{$table_name}_cats_bind");
        $this->db->dropTable("{$table_name}_props");
        $this->db->dropTable("{$table_name}_props_bind");
        $this->db->dropTable("{$table_name}_props_values");

        cmsCache::getInstance()->clean("content.types");

        return true;

    }

//============================================================================//
//============================================================================//

    public function getContentTypesCount(){

        if(!self::$all_ctypes){
            return 0;
        }

        return count(self::$all_ctypes);

    }

//============================================================================//
//============================================================================//

    public function loadAllCtypes() {

        if(isset(self::$all_ctypes)) {
            return $this;
        }

        self::$all_ctypes = $this->getContentTypesFiltered();

        return $this;

    }

    public function reloadAllCtypes() {
        self::$all_ctypes = $this->getContentTypesFiltered();
        return $this;
    }

    public function getContentTypes(){
        return self::$all_ctypes;
    }

    public function getContentTypesFiltered(){

        $this->useCache('content.types');

        return $this->get('content_types', function($item, $model){

            $item['options'] = cmsModel::yamlToArray($item['options']);
            $item['labels'] = cmsModel::yamlToArray($item['labels']);

            return $item;

        });

    }

    public function getContentTypesCountFiltered(){
        return $this->getCount('content_types');
    }

    public function getContentTypesNames(){

        if(!self::$all_ctypes){
            return false;
        }

        foreach (self::$all_ctypes as $ctype_id => $ctype) {
            $names[$ctype_id] = $ctype['name'];
        }

        return $names;

    }

//============================================================================//
//============================================================================//

    public function getContentType($id, $by_field='id'){

        if(!self::$all_ctypes){
            return false;
        }

        foreach (self::$all_ctypes as $ctype_id => $ctype) {

            if($ctype[$by_field] == $id) {
                return $ctype;
            }

        }

        return false;

    }

    public function getContentTypeByName($name){
        return $this->getContentType($name, 'name');
    }

    public function getContentTypeTableName($name){
        return $this->table_prefix . $name;
    }

//============================================================================//
//======================    ПАПКИ КОНТЕНТА   =================================//
//============================================================================//

    public function addContentFolder($ctype_id, $user_id, $title){

        return $this->insert('content_folders', array(
            'ctype_id' => $ctype_id,
            'user_id' => $user_id,
            'title' => $title
        ));

    }

    public function getContentFolders($ctype_id, $user_id){

        $this->
            filterEqual('ctype_id', $ctype_id)->
            filterEqual('user_id', $user_id);

        return $this->get('content_folders');

    }

    public function getContentFolder($id){

        return $this->getItemById('content_folders', $id);

    }

    public function updateContentFolder($id, $folder){

        return $this->update('content_folders', $id, $folder);

    }

    public function deleteContentFolder($folder, $is_delete_content=true){

        $ctype = $this->getContentType($folder['ctype_id']);

        $this->filterEqual('folder_id', $folder['id']);

        if (!$is_delete_content){
            $table_name = $this->table_prefix . $ctype['name'];
            $this->updateFiltered($table_name, array(
                'folder_id' => null
            ));
        }

        if ($is_delete_content){

            $items = $this->getContentItems($ctype['name']);

            if ($items){
                foreach($items as $item){
                    $this->deleteContentItem($ctype['name'], $item['id']);
                }
            }

        }

        return $this->delete('content_folders', $folder['id']);

    }

//============================================================================//
//=======================    ПОЛЯ КОНТЕНТА   =================================//
//============================================================================//

    public function getDefaultContentFieldOptions(){

        return array(
            'is_required' => 0,
            'is_digits' => 0,
            'is_number' => 0,
            'is_alphanumeric' => 0,
            'is_email' => 0,
            'is_unique' => 0,
            'label_pos' => 'left'
        );

    }

//============================================================================//
//============================================================================//

    public function addContentField($ctype_name, $field, $is_virtual=false){

        $content_table_name = $this->table_prefix . $ctype_name;
        $fields_table_name  = $this->table_prefix . $ctype_name . '_fields';

        $field['ordering'] = $this->getNextOrdering($fields_table_name);

        if (!$is_virtual){

            $field_class  = 'field'.string_to_camel('_', $field['type']);
            $field_parser = new $field_class(null, (isset($field['options']) ? array('options' => $field['options']) : null));

            $sql = "ALTER TABLE {#}{$content_table_name} ADD `{$field['name']}` {$field_parser->getSQL()}";
            $this->db->query($sql);

            if ($field['is_in_filter'] && $field_parser->allow_index){
                $this->db->addIndex($content_table_name, $field['name']);
            }

        }

        $id = $this->insert($fields_table_name, $field);

        // если есть опция полнотекстового поиска
        if(!$is_virtual && is_array($field['options']) && !empty($field['options']['in_fulltext_search'])){
            // получаем полнотекстовый индекс для таблицы, он может быть только один
            $fulltext_index = $this->db->getTableIndexes($content_table_name, 'FULLTEXT');
            if($fulltext_index){
                // название индекса
                $index_name = key($fulltext_index);
                // поля индекса
                $index_fields = $fulltext_index[$index_name];
                // ищем, нет ли такого поля уже в индексе, мало ли :-)
                $key = array_search($field['name'], $index_fields);
                // не нашли, добавляем
                if($key === false){
                    // удаляем старый индекс
                    $this->db->dropIndex($content_table_name, $index_name);
                    // создаем новый
                    $this->createFullTextIndex($ctype_name, $field['name']);
                }
            } else {
                $this->createFullTextIndex($ctype_name, $field['name']);
            }
        }

        return $id;

    }

//============================================================================//
//============================================================================//

    public function getContentFieldsCount($ctype_name){

        $table_name = $this->table_prefix . $ctype_name . '_fields';

        return $this->getCount($table_name);

    }

//============================================================================//
//============================================================================//

    public function getContentFields($ctype_name, $item_id = false){

        $table_name = $this->table_prefix . $ctype_name . '_fields';

        $this->ctype_name = $ctype_name;
        $this->item_id = $item_id;

        $this->orderBy('ordering');

        return $this->get($table_name, function($item, $model){

            $item['options'] = cmsModel::yamlToArray($item['options']);
            $item['options'] = array_merge($model->getDefaultContentFieldOptions(), $item['options']);
            $item['groups_read'] = cmsModel::yamlToArray($item['groups_read']);
            $item['groups_edit'] = cmsModel::yamlToArray($item['groups_edit']);
            $item['default'] = $item['values'];

            $rules = array();
            if ($item['options']['is_required']) {  $rules[] = array('required'); }
            if ($item['options']['is_digits']) {  $rules[] = array('digits'); }
            if ($item['options']['is_number']) {  $rules[] = array('number'); }
            if ($item['options']['is_alphanumeric']) {  $rules[] = array('alphanumeric'); }
            if ($item['options']['is_email']) {  $rules[] = array('email'); }

            if ($item['options']['is_unique']) {
                if (!$model->item_id){
                    $rules[] = array('unique', $model->table_prefix . $model->ctype_name, $item['name']);
                } else {
                    $rules[] = array('unique_exclude', $model->table_prefix . $model->ctype_name, $item['name'], $model->item_id);
                }
            }

            $item['rules'] = $rules;

            $field_class = "field" . string_to_camel('_', $item['type']);

            $handler = new $field_class($item['name']);

            $item['handler_title'] = $handler->getTitle();

            $handler->setOptions($item);

            $item['handler'] = $handler;

            return $item;

        }, 'name');

    }

    public function getRequiredContentFields($ctype_name){

        $fields = $this->getContentFields($ctype_name);

        $req_fields = array();

        foreach($fields as $field){
            if ($field['options']['is_required']) {
                $req_fields[] = $field;
            }
        }

        return $req_fields;

    }

//============================================================================//
//============================================================================//

    public function getContentField($ctype_name, $id){

        $table_name = $this->table_prefix . $ctype_name . '_fields';

        return $this->getItemById($table_name, $id, function($item, $model){

            $item['options'] = cmsModel::yamlToArray($item['options']);

            if (!$item['is_system']){
                $item['options'] = array_merge($model->getDefaultContentFieldOptions(), $item['options']);
            }

            $item['groups_read'] = cmsModel::yamlToArray($item['groups_read']);
            $item['groups_edit'] = cmsModel::yamlToArray($item['groups_edit']);

            $field_class = "field" . string_to_camel('_', $item['type']);

            $handler = new $field_class($item['name']);

            $item['parser_title'] = $handler->getTitle();

            $handler->setOptions($item);

            $item['parser'] = $handler;

            return $item;

        });

    }

//============================================================================//
//============================================================================//

    public function reorderContentFields($ctype_name, $fields_ids_list){

        $table_name = $this->table_prefix . $ctype_name . '_fields';

        $this->reorderByList($table_name, $fields_ids_list);

        return true;

    }

//============================================================================//
//============================================================================//

    public function updateContentField($ctype_name, $id, $field){

        $content_table_name = $this->table_prefix . $ctype_name;
        $fields_table_name = $this->table_prefix . $ctype_name . '_fields';

        $field_old = $this->getContentField($ctype_name, $id);

        if (!$field_old['is_system']){

            $new_lenght = ((isset($field['options']) && !empty($field['options']['max_length'])) ? $field['options']['max_length'] : false);
            $old_lenght = ((isset($field_old['options']) && !empty($field_old['options']['max_length'])) ? $field_old['options']['max_length'] : false);

            $field_class   = 'field'.string_to_camel('_', $field['type']);
            $field_handler = new $field_class(null, (isset($field['options']) ? array('options' => $field['options']) : null));

            if (($field_old['name'] != $field['name']) || ($field_old['type'] != $field['type']) || ($new_lenght != $old_lenght)){

                $sql = "ALTER TABLE  `{#}{$content_table_name}` CHANGE  `{$field_old['name']}` `{$field['name']}` {$field_handler->getSQL()}";
                $this->db->query($sql);

                if(($field_old['name'] != $field['name']) || ($field_old['type'] != $field['type'])){

                    // удаляем старый индекс
                    $this->db->dropIndex($content_table_name, $field_old['name']);

                    // добавляем новый
                    if ($field['is_in_filter'] && $field_handler->allow_index){
                        $this->db->addIndex($content_table_name, $field['name']);
                    }

                }

            }

            if ($field['is_in_filter'] && $field_handler->allow_index && !$field_old['is_in_filter']){
                $this->db->addIndex($content_table_name, $field['name']);
            }

            if (!$field['is_in_filter'] && $field_handler->allow_index && $field_old['is_in_filter']){
                $this->db->dropIndex($content_table_name, $field_old['name']);
            }

            // если есть опция полнотекстового поиска и ее значение изменилось
            if(is_array($field['options']) && array_key_exists('in_fulltext_search', $field['options'])){
                if($field['options']['in_fulltext_search'] != @$field_old['options']['in_fulltext_search']){
                    // получаем полнотекстовый индекс для таблицы, он может быть только один
                    $fulltext_index = $this->db->getTableIndexes($content_table_name, 'FULLTEXT');
                    if($fulltext_index){
                        // название индекса
                        $index_name = key($fulltext_index);
                        // поля индекса
                        $index_fields = $fulltext_index[$index_name];
                        // выключили опцию
                        if(!$field['options']['in_fulltext_search']){
                            $key = array_search($field['name'], $index_fields);
                            // нашли - удаляем из массива
                            if($key !== false){
                                unset($index_fields[$key]);
                                // удаляем индекс
                                $this->db->dropIndex($content_table_name, $index_name);
                                // и создаем новый
                                if($index_fields){
                                    $this->db->addIndex($content_table_name, $index_fields, '', 'FULLTEXT');
                                }
                            }
                        }
                        // включили опцию
                        if($field['options']['in_fulltext_search']){
                            // ищем, нет ли такого поля уже в индексе, мало ли :-)
                            $key = array_search($field['name'], $index_fields);
                            // не нашли, добавляем
                            if($key === false){
                                // удаляем старый индекс
                                $this->db->dropIndex($content_table_name, $index_name);
                                // создаем новый
                                $this->createFullTextIndex($ctype_name, $field['name']);
                            }
                        }

                    }
                }
            }

        }

        return $this->update($fields_table_name, $id, $field);

    }

    /**
     * Создает fulltext индекс согласно настроек полей типа контента
     * @param string $ctype_name Название типа контента
     * @param string|null $add_field Название поля, для которого принудительно нужно создать индекс
     * @return boolean
     */
    public function createFullTextIndex($ctype_name, $add_field=null) {

        // важен порядок индексов, поэтому создаем их так, как они будут в запросе
        // для этого получаем все поля этого типа контента
        $fields = $this->getContentFields($ctype_name);

        foreach ($fields as $field) {

            $is_text = in_array($field['type'], array('caption', 'text', 'html')) && ($field['handler']->getOption('in_fulltext_search') || $field['name'] == $add_field);
            if(!$is_text){ continue; }

            $index_fields[] = $field['name'];

        }

        if($index_fields){
            $this->db->addIndex($this->table_prefix . $ctype_name, $index_fields, '', 'FULLTEXT'); return true;
        }

        return false;

    }
//============================================================================//
//============================================================================//

	public function toggleContentFieldVisibility($ctype_name, $id, $mode, $is_visible){

		$fields_table_name = $this->table_prefix . $ctype_name . '_fields';

		return $this->update($fields_table_name, $id, array(
			$mode => $is_visible
		));

	}

//============================================================================//
//============================================================================//

    public function deleteContentField($ctype_name_or_id, $id){

        if (is_numeric($ctype_name_or_id)){
            $ctype = $this->getContentType($ctype_name_or_id);
            $ctype_name = $ctype['name'];
        } else {
            $ctype_name = $ctype_name_or_id;
        }

        $field = $this->getContentField($ctype_name, $id);

        if ($field['is_fixed']) { return false; }

        $content_table_name = $this->table_prefix . $ctype_name;
        $fields_table_name = $this->table_prefix . $ctype_name . '_fields';

        $this->delete($fields_table_name, $id);
        $this->reorder($fields_table_name);

        $this->db->dropTableField($content_table_name, $field['name']);

        return true;

    }

//============================================================================//
//============================================================================//

    public function getContentFieldsets($ctype_id){

        if (is_numeric($ctype_id)){
            $ctype = $this->getContentType($ctype_id);
            $ctype_name = $ctype['name'];
        } else {
            $ctype_name = $ctype_id;
        }

        $table_name = $this->table_prefix . $ctype_name . '_fields';

        $this->groupBy('fieldset');
        $this->orderBy('fieldset');

        $fieldsets = $this->get($table_name, function($item, $model){
            $item = $item['fieldset'];
            return $item;
        }, false);

        if ($fieldsets[0] == '') { unset($fieldsets[0]); }

        return $fieldsets;

    }

//============================================================================//
//============================    СВОЙСТВА   =================================//
//============================================================================//

    public function isContentPropsExists($ctype_name){

        $props_table_name = $this->table_prefix . $ctype_name . '_props';

        return (bool)$this->getCount($props_table_name);

    }

    public function getContentPropsBinds($ctype_name, $category_id=false){

        $props_table_name = $this->table_prefix . $ctype_name . '_props';
        $bind_table_name = $this->table_prefix . $ctype_name . '_props_bind';

        $this->selectOnly('p.*');
        $this->select('p.id', 'prop_id');
        $this->select('i.id', 'id');
        $this->select('i.cat_id', 'cat_id');

        $this->join($props_table_name, 'p', 'p.id = i.prop_id');

        if ($category_id){
            $this->filterEqual('cat_id', $category_id);
        }

        $this->orderBy('ordering');

        return $this->get($bind_table_name);

    }

    public function getContentProps($ctype_name, $category_id=false){

        $props_table_name = $this->table_prefix . $ctype_name . '_props';
        $bind_table_name = $this->table_prefix . $ctype_name . '_props_bind';

        if ($category_id){
            $this->selectOnly('p.*');
            $this->join($props_table_name, 'p', 'p.id = i.prop_id');
            $this->filterEqual('cat_id', $category_id);
            $this->orderBy('ordering');
            $table_name = $bind_table_name;
        } else {
            $table_name = $props_table_name;
        }

        return $this->get($table_name, function($item, $model){
            $item['options'] = cmsModel::yamlToArray($item['options']);
            return $item;
        });

    }

    public function getContentProp($ctype_name, $id){

        $props_table_name = $this->table_prefix . $ctype_name . '_props';
        $bind_table_name = $this->table_prefix . $ctype_name . '_props_bind';

        $prop = $this->getItemById($props_table_name, $id, function($item, $model){
            $item['options'] = cmsModel::yamlToArray($item['options']);
            return $item;
        });

        $this->filterEqual('prop_id', $id);

        $prop['cats'] = $this->get($bind_table_name, function($item, $model){
           return $item['cat_id'];
        });

        return $prop;

    }

    public function addContentProp($ctype_name, $prop){

        $table_name = $this->table_prefix . $ctype_name . '_props';

        $cats_list = $prop['cats']; unset($prop['cats']);

        $prop_id = $this->insert($table_name, $prop);

        $this->bindContentProp($ctype_name, $prop_id, $cats_list);

        return $prop_id;

    }

    public function updateContentProp($ctype_name, $id, $prop){

        $table_name = $this->table_prefix . $ctype_name . '_props';

        $old_prop = $this->getContentProp($ctype_name, $id);

        $missed_cats_list = array_diff($old_prop['cats'], $prop['cats']);
        $added_cats_list = array_diff($prop['cats'], $old_prop['cats']);

        if ($missed_cats_list) {
            foreach($missed_cats_list as $cat_id){
                $this->unbindContentProp($ctype_name, $id, $cat_id);
            }
        }

        if ($added_cats_list) {
            $this->bindContentProp($ctype_name, $id, $added_cats_list);
        }

        unset($prop['cats']);

        return $this->update($table_name, $id, $prop);

    }

	public function toggleContentPropFilter($ctype_name, $id, $is_in_filter){

		$table_name = $this->table_prefix . $ctype_name . '_props';

		return $this->update($table_name, $id, array(
			'is_in_filter' => $is_in_filter
		));

	}


    public function deleteContentProp($ctype_name_or_id, $prop_id){

        if (is_numeric($ctype_name_or_id)){
            $ctype = $this->getContentType($ctype_name_or_id);
            $ctype_name = $ctype['name'];
        } else {
            $ctype_name = $ctype_name_or_id;
        }

        $table_name = $this->table_prefix . $ctype_name . '_props';

        $prop = $this->getContentProp($ctype_name, $prop_id);

        foreach($prop['cats'] as $cat_id){
            $this->unbindContentProp($ctype_name, $prop_id, $cat_id);
        }

        $this->deleteContentPropValues($ctype_name, $prop_id);

        return $this->delete($table_name, $prop_id);

    }

    public function bindContentProp($ctype_name, $prop_id, $cats_list){

        $table_name = $this->table_prefix . $ctype_name . '_props_bind';

        foreach($cats_list as $cat_id){

            $this->filterEqual('cat_id', $cat_id);

            $ordering = $this->getNextOrdering($table_name);

            $this->insert($table_name, array(
                'prop_id' => $prop_id,
                'cat_id' => $cat_id,
                'ordering' => $ordering
            ));

        }

        return true;

    }

    public function unbindContentProp($ctype_name, $prop_id, $cat_id){

        $table_name = $this->table_prefix . $ctype_name . '_props_bind';

        $this->
            filterEqual('prop_id', $prop_id)->
            filterEqual('cat_id', $cat_id)->
            deleteFiltered($table_name);

        $this->
            filterEqual('cat_id', $cat_id)->
            reorder($table_name);

        return true;

    }

    public function unbindContentProps($ctype_name, $cat_id){

        $table_name = $this->table_prefix . $ctype_name . '_props_bind';

        $this->
            filterEqual('cat_id', $cat_id)->
            deleteFiltered($table_name);

        return true;

    }

    public function deleteContentPropValues($ctype_name, $prop_id){

        $table_name = $this->table_prefix . $ctype_name . '_props_values';

        $this->filterEqual('prop_id', $prop_id)->deleteFiltered($table_name);

        return true;

    }

    public function reorderContentProps($ctype_name, $props_ids_list){

        $table_name = $this->table_prefix . $ctype_name . '_props_bind';

        $this->reorderByList($table_name, $props_ids_list);

        return true;

    }

    public function getContentPropsFieldsets($ctype_id){

        if (is_numeric($ctype_id)){
            $ctype = $this->getContentType($ctype_id);
            $ctype_name = $ctype['name'];
        } else {
            $ctype_name = $ctype_id;
        }

        $table_name = $this->table_prefix . $ctype_name . '_props';

        $this->groupBy('fieldset');
        $this->orderBy('fieldset');

        $fieldsets = $this->get($table_name, function($item, $model){
            $item = $item['fieldset'];
            return $item;
        }, false);

        if (is_array($fieldsets) && $fieldsets[0] == '') { unset($fieldsets[0]); }

        return $fieldsets;

    }

    public function getPropsValues($ctype_name, $item_id){

        $table_name = $this->table_prefix . $ctype_name . '_props_values';

        $this->filterEqual('item_id', $item_id);

        return $this->get($table_name, function($item, $model){
            return $item['value'];
        }, 'prop_id');

    }

    public function addPropsValues($ctype_name, $item_id, $props_values){

        $table_name = $this->table_prefix . $ctype_name . '_props_values';

        foreach($props_values as $prop_id=>$value){

            $this->insert($table_name, array(
                'prop_id' => $prop_id,
                'item_id' => $item_id,
                'value' => $value
            ));

        }

    }

    public function updatePropsValues($ctype_name, $item_id, $props_values){

        $table_name = $this->table_prefix . $ctype_name . '_props_values';

        $props_ids = array_keys($props_values);

        $this->
            filterEqual('item_id', $item_id)->
            filterIn('prop_id', $props_ids)->
            deleteFiltered($table_name);

        $this->addPropsValues($ctype_name, $item_id, $props_values);

    }

    public function deletePropsValues($ctype_name, $item_id){

        $table_name = $this->table_prefix . $ctype_name . '_props_values';

        $this->
            filterEqual('item_id', $item_id)->
            deleteFiltered($table_name);

    }

//============================================================================//
//==============================   НАБОРЫ   ==================================//
//============================================================================//

    public function getContentDatasets($ctype_id=false, $only_visible=false){

        if ($ctype_id) { $this->filterEqual('ctype_id', $ctype_id); }

        if ($only_visible) { $this->filterEqual('is_visible', 1); }

        $this->orderBy('ordering');

        $this->useCache('content.datasets');

        $datasets = $this->get('content_datasets', function($item, $model){

            $item['groups_view'] = cmsModel::yamlToArray($item['groups_view']);
            $item['groups_hide'] = cmsModel::yamlToArray($item['groups_hide']);
            $item['filters'] = cmsModel::yamlToArray($item['filters']);
            $item['sorting'] = cmsModel::yamlToArray($item['sorting']);

            return $item;

        }, 'name');

        if ($only_visible && $datasets){
            $user = cmsUser::getInstance();
            foreach($datasets as $id=>$dataset){
                $is_user_view = $user->isInGroups($dataset['groups_view']);
                $is_user_hide = !empty($dataset['groups_hide']) && $user->isInGroups($dataset['groups_hide']) && !$user->is_admin;
                if (!$is_user_view || $is_user_hide) { unset($datasets[$id]); }
            }
        }

        return $datasets;

    }

    public function getContentDataset($id){

        return $this->getItemById('content_datasets', $id, function($item, $model){

            $item['groups_view'] = cmsModel::yamlToArray($item['groups_view']);
            $item['groups_hide'] = cmsModel::yamlToArray($item['groups_hide']);
            $item['filters'] = cmsModel::yamlToArray($item['filters']);
            $item['sorting'] = cmsModel::yamlToArray($item['sorting']);

            return $item;

        });

    }

    public function deleteContentDatasetIndex($ctype_name, $index_name) {

        // если используется в других датасетах, не удаляем
        if($this->getItemByField('content_datasets', 'index', $index_name)){
            return false;
        }

        return $this->db->dropIndex($this->table_prefix.$ctype_name, $index_name);

    }

    public function addContentDatasetIndex($dataset, $ctype_name) {

        $content_table_name = $this->table_prefix.$ctype_name;
        $index_name         = 'dataset_'.$dataset['name'];

        // поля для индекса
        $filters_fields = $sorting_fields = $fields = array();

        // создаем индекс
        // параметры выборки
        if($dataset['filters']){
            foreach ($dataset['filters'] as $filters) {
                if($filters && !in_array($filters['condition'], array('gt','lt','ge','le'))){
                    $filters_fields[] = $filters['field'];
                }
            }
            $filters_fields = array_unique($filters_fields);
        }
        // добавим условия, которые в каждой выборке
        $filters_fields[] = 'is_pub';
        $filters_fields[] = 'is_parent_hidden';
        $filters_fields[] = 'is_approved';
        // сортировка
        if($dataset['sorting']){
            foreach ($dataset['sorting'] as $sorting) {
                if($sorting){
                    $sorting_fields[] = $sorting['by'];
                }
            }
        }

        // если поле присутствует и в выборке и в сортировке, оставляем только в сортировке
        if($filters_fields){
            foreach ($filters_fields as $key => $field) {
                if(in_array($field, $sorting_fields)){
                    unset($filters_fields[$key]);
                }
            }
        }

        $fields = array_merge($filters_fields, $sorting_fields);

        if($fields == array('date_pub')){
            $index_name = 'date_pub';
        } elseif($fields == array('user_id','date_pub') || $fields == array('user_id')){
            $index_name = 'user_id';
        } else {

            // ищем индекс с таким же набором полей
            $is_found = false;
            $indexes = $this->db->getTableIndexes($content_table_name);
            foreach ($indexes as $_index_name => $_index_fields) {
                if($fields == $_index_fields){
                    $is_found = $_index_name; break;
                }
            }

            // нашли - используем его
            if($is_found){
                $index_name = $is_found;
            } else {

                // если нет, то создаем новый
                $this->db->addIndex($content_table_name, $fields, $index_name);

            }

        }

        return $index_name;

    }

    public function addContentDataset($dataset, $ctype){

        $table_name = 'content_datasets';

        $dataset['ctype_id'] = $ctype['id'];

        $this->filterEqual('ctype_id', $dataset['ctype_id']);

        $dataset['ordering'] = $this->getNextOrdering($table_name);

        $dataset['index'] = $this->addContentDatasetIndex($dataset, $ctype['name']);

        $id = $this->insert($table_name, $dataset);

        cmsCache::getInstance()->clean('content.datasets');

        return $id;

    }

    public function updateContentDataset($id, $dataset, $ctype, $old_dataset){

        $dataset['ctype_id'] = $ctype['id'];

        $success = $this->update('content_datasets', $id, $dataset);

        cmsCache::getInstance()->clean('content.datasets');

        if(($old_dataset['sorting'] != $dataset['sorting']) || ($old_dataset['filters'] != $dataset['filters'])){

            $this->deleteContentDatasetIndex($ctype['name'], $old_dataset['index']);

            $index = $this->addContentDatasetIndex($dataset, $ctype['name']);

            $this->update('content_datasets', $id, array('index'=>$index));

            cmsCache::getInstance()->clean('content.datasets');

        }

        return $success;

    }

	public function toggleContentDatasetVisibility($id, $is_visible){

		return $this->update('content_datasets', $id, array(
			'is_visible' => $is_visible
		));

	}

    public function reorderContentDatasets($fields_ids_list){

        $this->reorderByList('content_datasets', $fields_ids_list);

        cmsCache::getInstance()->clean('content.datasets');

        return true;

    }

    public function deleteContentDataset($id){

        $dataset = $this->getContentDataset($id);
        if(!$dataset){ return false; }

        $ctype = $this->getContentType($dataset['ctype_id']);
        if (!$ctype) { return false; }

        $this->delete('content_datasets', $id);

        $this->deleteContentDatasetIndex($ctype['name'], $dataset['index']);

        cmsCache::getInstance()->clean('content.datasets');

        return true;

    }

//============================================================================//
//=============================   КОНТЕНТ   ==================================//
//============================================================================//

    public function resetFilters(){
        parent::resetFilters();
        $this->approved_filtered = false;
        return $this;
    }

    public function enableApprovedFilter(){
        $this->approved_filter_disabled = false;
        return $this;
    }

    public function disableApprovedFilter(){
        $this->approved_filter_disabled = true;
        return $this;
    }

    public function enablePubFilter(){
        $this->pub_filter_disabled = false;
        return $this;
    }

    public function disablePubFilter(){
        $this->pub_filter_disabled = true;
        return $this;
    }

    public function filterApprovedOnly(){

        if ($this->approved_filtered) { return $this; }

        // Этот фильтр может применяться при подсчете числа записей
        // и при выборке самих записей
        // используем флаг чтобы фильтр не применился дважды
        $this->approved_filtered = true;

        return $this->filterEqual('is_approved', 1);

    }

	public function filterPublishedOnly(){

		if ($this->pub_filtered) { return $this; }

        $this->pub_filtered = true;

        return $this->filterEqual('is_pub', 1);

	}

    public function filterByModeratorTask($moderator_id, $ctype_name){

        return $this->filter("(EXISTS (SELECT item_id FROM {#}moderators_tasks WHERE moderator_id='{$moderator_id}' AND ctype_name='{$ctype_name}' AND item_id=i.id))");

    }

//============================================================================//

    public function filterPropValue($ctype_name, $prop, $value){

        $table_name = $this->table_prefix . $ctype_name . '_props_values';
        $table_alias = "p{$prop['id']}";

        if (is_array($value)){

            $value_condition = array();
            $glue = 'OR';

            if (isset($value['from']) || isset($value['to'])){

                if (empty($value['from']) && empty($value['to'])) { return $this; }

                if (isset($value['from'])){
                    $v = (int)$this->db->escape($value['from']);
                    $value_condition[] = "{$table_alias}.value >= {$v}";
                }

                if (isset($value['to'])){
                    $v = (int)$this->db->escape($value['to']);
                    $value_condition[] = "{$table_alias}.value <= {$v}";
                }

                $glue = 'AND';

            } else {

                foreach($value as $v){
                    if (!$v) { continue; }
                    $v = $this->db->escape($v);
                    $value_condition[] = "{$table_alias}.value = '{$v}'";
                }

            }

            $value_condition = implode(" {$glue} ", $value_condition);

        } else {

            $value = $this->db->escape($value);
            $value_condition = "{$table_alias}.value = '{$value}'";

        }

        $on_condition = "({$table_alias}.item_id = i.id AND {$table_alias}.prop_id = {$prop['id']} AND ({$value_condition}))";

        $this->join($table_name, $table_alias, $on_condition);

        return $this;

    }

//============================================================================//
//============================================================================//

    public function addContentItem($ctype, $item, $fields){

        $table_name = $this->table_prefix . $ctype['name'];

        $item['user_id'] = empty($item['user_id']) ? cmsUser::getInstance()->id : $item['user_id'];

        if (!empty($item['props'])){
            $props_values = $item['props'];
            unset($item['props']);
        }

        if(!isset($item['category_id'])){
            $item['category_id'] = 0;
        }

        if (!empty($item['new_category'])){
            $category = $this->addCategory($ctype['name'], array(
                'title' => $item['new_category'],
                'parent_id' => $item['category_id']
            ));
            $item['category_id'] = $category['id'];
        }

        unset($item['new_category']);

        if (!empty($item['new_folder'])){
            $folder_id = $this->addContentFolder($ctype['id'], $item['user_id'], $item['new_folder']);
            $item['folder_id'] = $folder_id;
        }

        unset($item['new_folder']);

		$add_cats = array();

		if (isset($item['add_cats'])){
			$add_cats = $item['add_cats'];
			unset($item['add_cats']);
		}

        $item['id'] = $this->insert($table_name, $item);

		$this->updateContentItemCategories($ctype['name'], $item['id'], $item['category_id'], $add_cats);

        if (isset($props_values)){
            $this->addPropsValues($ctype['name'], $item['id'], $props_values);
        }

        if (!isset($item['slug'])){
            $item = $this->getContentItem($ctype['name'], $item['id']);
            $item['slug'] = $this->getItemSlug($ctype, $item, $fields);
        }

        $this->update($table_name, $item['id'], array(
            'slug' => $item['slug'],
            'date_last_modified' => null
        ));

        cmsCache::getInstance()->clean("content.list.{$ctype['name']}");

        return $item;

    }

//============================================================================//
//============================================================================//

    public function updateContentItem($ctype, $id, $item, $fields){

        $table_name = $this->table_prefix . $ctype['name'];

        if (!$ctype['is_fixed_url']){

            if ($ctype['is_auto_url']){
                $item['slug'] = $this->getItemSlug($ctype, $item, $fields);
            } else {
                $item['slug'] = lang_slug( $item['slug'] );
            }

            $this->update($table_name, $id, array( 'slug' => $item['slug'] ));

        }

        if (!empty($item['new_category'])){
            $category = $this->addCategory($ctype['name'], array(
                'title' => $item['new_category'],
                'parent_id' => $item['category_id']
            ));
            $item['category_id'] = $category['id'];
        }

        unset($item['new_category']);

        if (!empty($item['new_folder'])){
            $folder_id = $this->addContentFolder($ctype['id'], (empty($item['user_id']) ? cmsUser::getInstance()->id : $item['user_id']), $item['new_folder']);
            $item['folder_id'] = $folder_id;
        }

        unset($item['new_folder']);
        unset($item['folder_title']);

        // удаляем поле SLUG из перечня полей для апдейта,
        // посколько оно могло быть изменено ранее
        $update_item = $item; unset($update_item['slug']);

        if (!empty($update_item['props'])){
            $this->updatePropsValues($ctype['name'], $id, $update_item['props']);
        }

		unset($update_item['props']);
        unset($update_item['user']);
        unset($update_item['user_nickname']);

		$add_cats = array();

		if (isset($update_item['add_cats'])){
			$add_cats = $update_item['add_cats'];
			unset($update_item['add_cats']);
		}

        $update_item['date_last_modified'] = null;

        $this->update($table_name, $id, $update_item);

		$this->updateContentItemCategories($ctype['name'], $id, $item['category_id'], $add_cats);

        cmsCache::getInstance()->clean("content.list.{$ctype['name']}");
        cmsCache::getInstance()->clean("content.item.{$ctype['name']}");

        return $item;

    }

    public function updateContentItemTags($ctype_name, $id, $tags){

        $table_name = $this->table_prefix . $ctype_name;

        $this->update($table_name, $id, array(
            'tags' => $tags
        ));

    }

//============================================================================//
//============================================================================//

    public function getItemSlug($ctype, $item, $fields, $check_slug = true){

        $pattern = trim($ctype['url_pattern'], '/');

        preg_match_all('/{([a-zA-Z0-9\_]+)}/i', $pattern, $matches);

        if (!$matches) { return lang_slug($item['id']); }

        list($tags, $names) = $matches;

        if (in_array('category', $names)){
            $category = $this->getCategory($ctype['name'], $item['category_id']);
            $pattern = str_replace('{category}', $category['slug'], $pattern);
            unset($names[ array_search('category', $names) ]);
        }

        $pattern = trim($pattern, '/');

        foreach($names as $idx=>$field_name){
            if (!empty($item[$field_name])){

                $value = str_replace('/', '', $item[$field_name]);

                if (isset($fields[$field_name])){
                    $value = $fields[$field_name]['handler']->getStringValue($value);
                    $value = trim($value, '/');
                }

                $pattern = str_replace($tags[$idx], $value, $pattern);

            }
        }

        $slug = lang_slug($pattern);

        if(!$check_slug){
            return $slug;
        }

        if($this->filterNotEqual('id', $item['id'])->
                filterEqual('slug', $slug)->
                getFieldFiltered($this->table_prefix.$ctype['name'], 'id')){
            $slug .= uniqid();
        }

        return $slug;

    }

//============================================================================//
//============================================================================//

	public function getContentItemCategories($ctype_name, $id){

		$table_name = $this->table_prefix . $ctype_name . "_cats_bind";

		return $this->filterEqual('item_id', $id)->get($table_name, function($item, $model){
			return $item['category_id'];
		}, false);

	}

    public function moveContentItemsToCategory($ctype, $category_id, $items_ids, $fields){

        $table_name = $this->table_prefix . $ctype['name'];
        $binds_table_name = $this->table_prefix . $ctype['name'] . "_cats_bind";

		$items = $this->filterIn('id', $items_ids)->get($table_name);

		foreach($items as $item){

			$this->
				filterEqual("item_id", $item['id'])->
				filterEqual("category_id", $item['category_id'])->
				deleteFiltered($binds_table_name);

			$is_bind_exists = $this->
								filterEqual("item_id", $item['id'])->
								filterEqual("category_id", $category_id)->
								getCount($binds_table_name, 'item_id');

			$this->resetFilters();

			if (!$is_bind_exists){

				$this->insert($binds_table_name, array(
					'item_id' => $item['id'],
					'category_id' => $category_id
				));

			}

			$item['category_id'] = $category_id;

			if (!$ctype['is_fixed_url'] && $ctype['is_auto_url']){
				$item['slug'] = $this->getItemSlug($ctype, $item, $fields);
				$this->update($table_name, $item['id'], array( 'slug' => $item['slug'] ));
			}

		}

        $this->filterIn('id', $items_ids)->updateFiltered($table_name, array(
            'category_id' => $category_id
        ));

        cmsCache::getInstance()->clean("content.list.{$ctype['name']}");
        cmsCache::getInstance()->clean("content.item.{$ctype['name']}");

        return true;

    }

	public function updateContentItemCategories($ctype_name, $id, $category_id, $add_cats){

		$table_name = $this->table_prefix . $ctype_name . "_cats_bind";

		$new_cats = empty($add_cats) ? array() : $add_cats;

		if (!$category_id) { $category_id = 1; }

		if (!in_array($category_id, $new_cats)){
			$new_cats[] = $category_id;
		}

		$current_cats = $this->
							filterEqual("item_id", $id)->
							get($table_name, function($item, $model){
								return $item['category_id'];
							}, false);

		if ($current_cats){
			foreach($current_cats as $current_cat_id){

				if (!in_array($current_cat_id, $new_cats)){
					$this->
						filterEqual("item_id", $id)->
						filterEqual("category_id", $current_cat_id)->
						deleteFiltered($table_name);
				}

			}
		}

		foreach($new_cats as $new_cat_id){
			if (!$current_cats || !in_array($new_cat_id, $current_cats)){
				$this->insert($table_name, array(
					'item_id' => $id,
					'category_id' => $new_cat_id
				));
			}
		}

	}

//============================================================================//
//============================================================================//

    public function deleteContentItem($ctype_name, $id){

        $table_name = $this->table_prefix . $ctype_name;

        $item = $this->getContentItem($ctype_name, $id);
        if(!$item){ return false; }

        cmsEventsManager::hook('content_before_delete', array('ctype_name'=>$ctype_name, 'item'=>$item));
        cmsEventsManager::hook("content_{$ctype_name}_before_delete", $item);

        $fields = $this->getContentFields($ctype_name, $id);

        foreach($fields as $field){
            $field['handler']->delete($item[$field['name']]);
        }

        cmsCore::getController('activity')->deleteEntry('content', "add.{$ctype_name}", $id);

        cmsCore::getModel('comments')->deleteComments('content', $ctype_name, $id);
        cmsCore::getModel('rating')->deleteVotes('content', $ctype_name, $id);
        cmsCore::getModel('tags')->deleteTags('content', $ctype_name, $id);

        cmsCache::getInstance()->clean("content.list.{$ctype_name}");
        cmsCache::getInstance()->clean("content.item.{$ctype_name}");

        $this->closeModeratorTask($ctype_name, $id, false);

        $this->deletePropsValues($ctype_name, $id);

        $this->filterEqual('item_id', $item['id'])->deleteFiltered($table_name.'_cats_bind');

        $success = $this->delete($table_name, $id);

        if($success){
            cmsEventsManager::hook('content_after_delete', array('ctype_name'=>$ctype_name, 'item'=>$item));
            cmsEventsManager::hook("content_{$ctype_name}_after_delete", $item);
        }

        return $success;

    }

    public function deleteUserContent($user_id){

        $ctypes = $this->getContentTypes();

        foreach($ctypes as $ctype){

            $items = $this->filterEqual('user_id', $user_id)->getContentItems($ctype['name']);

            if (is_array($items)){
                foreach($items as $item){
                    $this->deleteContentItem($ctype['name'], $item['id']);
                }
            }

        }

        $this->filterEqual('user_id', $user_id)->deleteFiltered('content_folders');
        $this->filterEqual('user_id', $user_id)->deleteFiltered('moderators');

    }

//============================================================================//
//============================================================================//

    public function getContentItemsCount($ctype_name){

        $table_name = $this->table_prefix . $ctype_name;

        if (!$this->privacy_filter_disabled) { $this->filterPrivacy(); }
        if (!$this->approved_filter_disabled) { $this->filterApprovedOnly(); }
        if (!$this->pub_filter_disabled) { $this->filterPublishedOnly(); }

        return $this->getCount($table_name);

    }

//============================================================================//
//============================================================================//

    public function getContentItems($ctype_name){

        $table_name = $this->table_prefix . $ctype_name;

        $this->select('u.nickname', 'user_nickname');
        $this->select('f.title', 'folder_title');
        $this->join('{users}', 'u FORCE INDEX (PRIMARY)', 'u.id = i.user_id');
        $this->joinLeft('content_folders', 'f', 'f.id = i.folder_id');

        if (!$this->privacy_filter_disabled) { $this->filterPrivacy(); }
        if (!$this->approved_filter_disabled) { $this->filterApprovedOnly(); }
        if (!$this->pub_filter_disabled) { $this->filterPublishedOnly(); }

        if (!$this->order_by){ $this->orderBy('date_pub', 'desc')->forceIndex('date_pub'); }

        $this->useCache("content.list.{$ctype_name}");

        $user = cmsUser::getInstance();

        return $this->get($table_name, function($item, $model) use ($user){

            $item['user'] = array(
                'id'        => $item['user_id'],
                'nickname'  => $item['user_nickname'],
                'is_friend' => $user->isFriend($item['user_id'])
            );

            return $item;

        });

    }

//============================================================================//
//============================================================================//

    public function getContentItem($ctype_name, $id, $by_field='id'){

        $table_name = $this->table_prefix . $ctype_name;

        $this->select('u.nickname', 'user_nickname');
        $this->select('u.avatar', 'user_avatar');
        $this->select('f.title', 'folder_title');

        $this->join('{users}', 'u', 'u.id = i.user_id');
        $this->joinLeft('content_folders', 'f', 'f.id = i.folder_id');

        $this->useCache("content.item.{$ctype_name}");

        return $this->getItemByField($table_name, $by_field, $id, function($item, $model){

            $item['user'] = array(
                'id' => $item['user_id'],
                'nickname' => $item['user_nickname']
            );

            return $item;

        }, $by_field);

    }

    public function getContentItemBySLUG($ctype_name, $slug){

        return $this->getContentItem($ctype_name, $slug, 'slug');

    }

//============================================================================//
//============================================================================//

    public function getUserContentItemsCount($ctype_name, $user_id, $is_only_approved = true){

        $this->filterEqual('user_id', $user_id);

		if (!$is_only_approved) { $this->approved_filter_disabled = true; }

        $count = $this->getContentItemsCount( $ctype_name );

        $this->resetFilters();

        return $count;

    }

    public function getUserContentCounts($user_id, $is_filter_hidden=false){

        $counts = array();

        $ctypes = $this->getContentTypes();

        $this->filterEqual('user_id', $user_id);

        if ($is_filter_hidden){
            $this->filterHiddenParents();
        }

        if (!$is_filter_hidden){
            $this->disableApprovedFilter();
			$this->disablePubFilter();
        }

        foreach($ctypes as $ctype){

            $count = $this->getContentItemsCount( $ctype['name'] );

            if ($count) {

                $counts[ $ctype['name'] ] = array(
                    'count' => $count,
                    'is_in_list' => $ctype['options']['profile_on'],
                    'title' => empty($ctype['labels']['profile']) ? $ctype['title'] : $ctype['labels']['profile']
                );

            }

        }

        $this->resetFilters();

        return $counts;

    }

//============================================================================//
//============================================================================//

	public function publishDelayedContentItems($ctype_name){

		return $this->
					filterNotEqual('is_pub', 1)->
					filter('i.date_pub <= NOW()')->
					updateFiltered($this->table_prefix.$ctype_name, array(
						'is_pub' => 1
					));

	}

	public function hideExpiredContentItems($ctype_name){

		return $this->
					filterEqual('is_pub', 1)->
					filterNotNull('date_pub_end')->
					filter('i.date_pub_end <= NOW()')->
					updateFiltered($this->table_prefix.$ctype_name, array(
						'is_pub' => 0
					));

	}

	public function deleteExpiredContentItems($ctype_name){

        return $this->
                    filterNotNull('date_pub_end')->
                    filter('i.date_pub_end <= NOW()')->
                    get($this->table_prefix.$ctype_name, function($item, $model) use($ctype_name){
                        $model->deleteContentItem($ctype_name, $item['id']);
                        return $item['id'];
                    });

	}

	public function toggleContentItemPublication($ctype_name, $id, $is_pub){

		return $this->update($this->table_prefix.$ctype_name, $id, array(
			'is_pub' => $is_pub
		));

	}

	public function incrementHitsCounter($ctype_name, $id){

		$this->filterEqual('id', $id)->increment($this->table_prefix.$ctype_name, 'hits_count');

	}

//============================================================================//
//============================================================================//

    public function deleteCategory($ctype_name, $id, $is_delete_content=false){

        $category = $this->getCategory($ctype_name, $id);

        $this->filterCategory($ctype_name, $category, true);

        if (!$is_delete_content){
            $table_name = $this->table_prefix . $ctype_name;
            $this->updateFiltered($table_name, array(
                'category_id' => 1
            ));
        }

        if ($is_delete_content){

            $items = $this->getContentItems($ctype_name);

            if ($items){
                foreach($items as $item){
                    $this->deleteContentItem($ctype_name, $item['id']);
                }
            }

        }

        $this->unbindContentProps($ctype_name, $id);

        parent::deleteCategory($ctype_name, $id);

    }

//============================================================================//
//============================================================================//

    public function getRatingTarget($ctype_name, $id){

        $table_name = $this->table_prefix . $ctype_name;

        $item = $this->getItemById($table_name, $id);

        return $item;

    }

    public function updateRating($ctype_name, $id, $rating){

        $table_name = $this->table_prefix . $ctype_name;

        $this->update($table_name, $id, array('rating' => $rating));

        cmsCache::getInstance()->clean("content.list.{$ctype_name}");
        cmsCache::getInstance()->clean("content.item.{$ctype_name}");

    }

//============================================================================//
//============================================================================//

    public function updateCommentsCount($ctype_name, $id, $comments_count){

        $table_name = $this->table_prefix . $ctype_name;

        $this->update($table_name, $id, array('comments' => $comments_count));

        cmsCache::getInstance()->clean("content.list.{$ctype_name}");
        cmsCache::getInstance()->clean("content.item.{$ctype_name}");

        return true;

    }

    public function getTargetItemInfo($ctype_name, $id){

        $item = $this->getContentItem($ctype_name, $id);

        if (!$item){ return false; }

        return array(
            'url' => href_to_rel($ctype_name, $item['slug'].'.html'),
            'title' => $item['title'],
            'is_private' => $item['is_private'] || $item['is_parent_hidden']
        );

    }

//============================================================================//
//============================================================================//

    public function toggleParentVisibility($parent_type, $parent_id, $is_hidden){

        $ctypes_names = $this->getContentTypesNames();

        $is_hidden = $is_hidden ? 1 : null;

        foreach($ctypes_names as $ctype_name){

            $table_name = $this->table_prefix . $ctype_name;

            $this->
                filterEqual('parent_type', $parent_type)->
                filterEqual('parent_id', $parent_id)->
                updateFiltered($table_name, array('is_parent_hidden' => $is_hidden));

        }

    }

//============================================================================//
//=========================    МОДЕРАТОРЫ   ==================================//
//============================================================================//

    public function getContentTypeModerators($ctype_name){

        $this->joinUser();

        $this->filterEqual('ctype_name', $ctype_name);

        $this->orderBy('id');

        return $this->get('moderators', false, 'user_id');

    }

    public function getContentTypeModerator($id){

        $this->joinUser();

        return $this->getItemById('moderators', $id);

    }

    public function userIsContentTypeModerator($ctype_name, $user_id){

        $this->filterEqual('ctype_name', $ctype_name);
        $this->filterEqual('user_id', $user_id);

        $is_moderator = (bool)$this->getCount('moderators');

        $this->resetFilters();

        return $is_moderator;

    }

    public function addContentTypeModerator($ctype_name, $user_id){

        $id = $this->insert('moderators', array(
            'ctype_name' => $ctype_name,
            'user_id' => $user_id,
            'date_assigned' => ''
        ));

        return $this->getContentTypeModerator($id);

    }

    public function deleteContentTypeModerator($ctype_name, $user_id){

        return $this->
                    filterEqual('ctype_name', $ctype_name)->
                    filterEqual('user_id', $user_id)->
                    deleteFiltered('moderators');

    }

    public function getNextModeratorId($ctype_name){

        $id = $this->filterEqual('ctype_name', $ctype_name)->
                    orderBy('count_idle', 'asc')->
                    getFieldFiltered('moderators', 'user_id');

        if (!$id){

            $id = $this->filterEqual('is_admin', 1)->
                        getFieldFiltered('{users}', 'id');
            // проверяем наличие администратора в таблице модераторов
            // и если его там нет, добавляем
            if(!$this->getItemById('moderators', $id)){
                $this->addContentTypeModerator($ctype_name, $id);
            }

        }

        return $id;

    }

    public function approveContentItem($ctype_name, $id, $moderator_user_id){

        $table_name = $this->table_prefix . $ctype_name;

        $this->update($table_name, $id, array(
            'is_approved' => 1,
            'approved_by' => $moderator_user_id,
            'date_approved' => ''
        ));

        return true;

    }

    public function getModeratorTask($ctype_name, $id){

        return $this->
                    filterEqual('ctype_name', $ctype_name)->
                    filterEqual('item_id', $id)->
                    getItem('moderators_tasks');

    }

    public function addModeratorTask($ctype_name, $user_id, $is_new_item, $item){

        $this->
            filterEqual('user_id', $user_id)->
            filterEqual('ctype_name', $ctype_name)->
            increment('moderators', 'count_idle');

        return $this->insert('moderators_tasks', array(
            'moderator_id' => $user_id,
            'author_id' => $item['user_id'],
            'item_id' => $item['id'],
            'ctype_name' => $ctype_name,
            'title' => $item['title'],
            'url' => href_to($ctype_name, $item['slug'].".html"),
            'date_pub' => '',
            'is_new_item' => $is_new_item
        ));

    }

    public function closeModeratorTask($ctype_name, $id, $is_approved){

        $user = cmsUser::getInstance();

        $counter_field = $is_approved ? 'count_approved' : 'count_deleted';

        $task = $this->getModeratorTask($ctype_name, $id);

        $this->
            filterEqual('user_id', $user->id)->
            filterEqual('ctype_name', $ctype_name)->
            increment('moderators', $counter_field);

        $this->
            filterEqual('user_id', $task['moderator_id'])->
            filterEqual('ctype_name', $ctype_name)->
            filterGt('count_idle', 0)->
            decrement('moderators', 'count_idle');

        return $this->
                filterEqual('ctype_name', $ctype_name)->
                filterEqual('item_id', $id)->
                deleteFiltered('moderators_tasks');

    }

//============================================================================//
//============================================================================//

}
