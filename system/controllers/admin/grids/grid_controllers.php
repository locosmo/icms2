<?php

function grid_controllers($controller){

    $denied = array(
        'admin','auth','markitup','images','content','moderation','users','wall','tags'
    );

    $options = array(
        'order_by' => 'title',
        'is_pagination' => false,
    );

    $columns = array(
        'title' => array(
            'title' => LANG_TITLE,
            'href' => href_to($controller->name, 'controllers', array('edit', '{name}')),
            'filter' => 'like'
        ),
        'is_enabled' => array(
            'title' => LANG_IS_ENABLED,
			'flag' => true,
			'flag_toggle' => href_to($controller->name, 'controllers', array('toggle', '{id}')),
            'width' => 80,
            'handler' => function ($v, $row) use ($denied){
                if(in_array($row['name'], $denied)){
                    return '';
                }
                return $v;
            }
        ),
        'version' => array(
            'title' => LANG_VERSION,
            'width' => 150,
            'filter' => 'like'
        ),
        'author' => array(
            'title' => LANG_AUTHOR,
            'width' => 150,
            'href' => '{url}',
            'filter' => 'like'
        )
    );

    $actions = array(
        array(
            'title' => LANG_CONFIG,
            'class' => 'config',
            'href' => href_to($controller->name, 'controllers', array('edit', '{name}')),
            'handler' => function($row){
                return $row['is_backend'];
            }
        ),
        array(
            'title' => LANG_DELETE,
            'class' => 'delete',
            'confirm' => LANG_CP_DELETE_COMPONENT_CONFIRM,
            'href' => href_to($controller->name, 'controllers_delete', array('{name}')),
            'handler' => function($row){
                return $row['is_external'];
            }
        )
    );

    return array(
        'options' => $options,
        'columns' => $columns,
        'actions' => $actions
    );

}

