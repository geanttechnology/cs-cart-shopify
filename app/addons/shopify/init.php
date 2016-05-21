<?php
/**
 * @author Shikhar kumar (shikhar.kr@gmail.com)
 * Copyright 2012-2014 Creative House WLL, Kuwait
 */

use Tygh\Registry;

if (!defined('BOOTSTRAP')) { die('Access denied'); }

fn_register_hooks(
	'change_order_status',
	'update_product_post'
	);