<?php
/**
*
* Prune PMs extension for the phpBB Forum Software package.
*
* @copyright (c) 2020 Rich McGirr (RMcGirr83)
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace rmcgirr83\prunepms\acp;

class main_info
{
	public function module()
	{
		return [
			'filename'	=> '\rmcgirr83\prunepms\acp\main_module',
			'title'		=> 'ACP_PPMS_TITLE',
			'version'	=> '1.0.0',
			'modes'		=> [
				'main'	=> [
					'title' => 'ACP_PPMS_TITLE',
					'auth' => 'ext_rmcgirr83/prunepms && acl_a_user',
					'cat' => ['ACP_CAT_USERS'],
				],
			],
		];
	}
}
