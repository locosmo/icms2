<?php if (!isset($is_expanded)){ $is_expanded = false; } unset($filters['user_id']); ?>
<div class="filter-panel gui-panel <?php echo $css_prefix;?>-filter">
    <div class="filter-link" <?php if($filters || $is_expanded){ ?>style="display:none"<?php } ?>>
        <a href="javascript:toggleFilter()"><span><?php echo LANG_SHOW_FILTER; ?></span></a>
    </div>
    <div class="filter-container" <?php if(!$filters && !$is_expanded){ ?>style="display:none"<?php } ?>>
		<div class="filter-close">
            <a href="javascript:toggleFilter();"><span><?php echo LANG_CLOSE; ?></span></a>
        </div>
        <form action="<?php echo cmsCore::getInstance()->uri_absolute; ?>" method="post">
            <?php echo html_input('hidden', 'page', 1); ?>
            <div class="fields">
                <?php $fields_count = 0; ?>
                <?php foreach($fields as $name => $field){ ?>
                    <?php if (!$field['is_in_filter']){ continue; } ?>
                    <?php $value = isset($filters[$name]) ? $filters[$name] : null; ?>
                    <?php $output = $field['handler']->getFilterInput($value); ?>
                    <?php if (!$output){ continue; } ?>
                    <?php $fields_count++; ?>
                    <div class="field ft_<?php echo $field['type']; ?> f_<?php echo $field['name']; ?>">
                        <div class="title"><?php echo $field['title']; ?></div>
                        <div class="value">
                            <?php echo $output; ?>
                        </div>
                    </div>
                <?php } ?>
                <?php if (!empty($props_fields)){ ?>
                    <?php foreach($props as $prop){ ?>
                        <?php
                            if (!$prop['is_in_filter']){ continue; }
                            $fields_count++;
                            $field = $props_fields[$prop['id']];
                            $field->setName("p{$prop['id']}");
                            if ($prop['type'] == 'list' && !empty($prop['options']['is_filter_multi'])){ $field->setOption('filter_multiple', true); }
                            if ($prop['type'] == 'number' && !empty($prop['options']['is_filter_range'])){ $field->setOption('filter_range', true); }
                            $value = isset($filters["p{$prop['id']}"]) ? $filters["p{$prop['id']}"] : null;
                        ?>
                        <div class="field ft_<?php echo $prop['type']; ?> f_prop_<?php echo $prop['id']; ?>">
                            <div class="title"><?php echo $prop['title']; ?></div>
                            <div class="value">
                                <?php echo $field->getFilterInput($value); ?>
                            </div>
                        </div>
                    <?php } ?>
                <?php } ?>
            </div>
            <?php if ($fields_count) { ?>
                <div class="buttons">
                    <?php echo html_submit(LANG_FILTER_APPLY); ?>
                    <?php if (sizeof($filters)){ ?>
                        <div class="link">
                            <a href="<?php echo is_array($page_url) ? $page_url['base'] : $page_url; ?>"><?php echo LANG_CANCEL; ?></a>
                        </div>
                        <div class="link">
                            # <a href="<?php echo is_array($page_url) ? $page_url['base'] : $page_url; ?>?<?php echo http_build_query($filters); ?>"><?php echo LANG_FILTER_URL; ?></a>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </form>
    </div>
</div>
