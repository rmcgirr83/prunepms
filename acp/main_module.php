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

class main_module
{
	var $u_action;

	public function main($id, $mode)
	{

		global $user, $template, $phpbb_log, $request;
		global $phpbb_root_path, $phpEx;

		if (!function_exists('validate_date'))
		{
			include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
		}

		$this->tpl_name = 'acp_prunepms';
		$this->page_title = 'ACP_PPMS';

		$prune = (isset($_POST['prune'])) ? true : false;

		$ignore_admin_mods = $this->get_admin_mods();
		$prune_date = $request->variable('pms_before', '');
		$error = '';

		if ($prune && validate_date($prune_date))
		{
			$error = $user->lang('PPMS_INVALID_DATE');
		}

		if ($prune && empty($error))
		{
			$ignore_ams = $request->variable('ignore_ams', 0);

			if ($ignore_ams)
			{
				$ignore_ams = $ignore_admin_mods;
			}

			// We count the PMs which will be pruned...
			$pm_msg_ids = $this->get_prune_pms($prune_date, $ignore_ams);

			if (confirm_box(true))
			{
				if (count($pm_msg_ids))
				{
					$this->delete_pms($pm_msg_ids);

					$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_PPMS_DELETED', false, array(count($pm_msg_ids)));
					$msg = $user->lang('PMS_DELETED_SUCCESS');
				}
				else
				{
					$msg = $user->lang('PMS_PRUNE_FAILURE');
				}

				trigger_error($msg . adm_back_link($this->u_action));
			}
			else
			{
				if (!count($pm_msg_ids))
				{
					trigger_error($user->lang('PMS_PRUNE_FAILURE') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				$prune_date = explode('-', $prune_date);
				$prune_date = gmmktime(0, 0, 0, (int) $prune_date[1], (int) $prune_date[0], (int) $prune_date[2]);
				$prune_date = $user->format_date($prune_date, 'M d Y');

				$template->assign_vars(array(
					'S_COUNT_PMS'				=> count($pm_msg_ids),
					'L_PMS_TO_PURGE'		=> $user->lang('PMS_TO_PURGE', count($pm_msg_ids), $prune_date),
				));

				confirm_box(false, $user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
					'i'				=> $id,
					'mode'			=> $mode,
					'prune'			=> 1,

					'pms_before' 	=> $request->variable('pms_before', ''),

				)), 'confirm_body_prunepms.html');
			}
		}

		$pm_stats = $this->get_pm_stats();
		$pm_count = $pm_stats['pm_count'];
		$pm_oldest_time = $user->format_date($pm_stats['oldest_message_time']);
		$pm_newest_time = $user->format_date($pm_stats['newest_message_time']);

		$template->assign_vars(array(
			'ERROR'			=> $error,
			'PM_COUNT'		=> $pm_count,
			'L_PM_TOTAL_STATS'	=> $user->lang('PM_TOTAL_STATS', $pm_count, $pm_oldest_time, $pm_newest_time),
			'U_ACTION'		=> $this->u_action,
		));
	}

	private function get_pm_stats()
	{
		global $db;

		$pm_stats = array();

		// get total count of PMs
		$sql = 'SELECT COUNT(msg_id) as msg_id_count
			FROM ' . PRIVMSGS_TABLE;
		$result = $db->sql_query($sql);
		$pm_stats['pm_count'] = (int) $db->sql_fetchfield('msg_id_count');
		$db->sql_freeresult($result);

		// get oldest message date
		$sql = 'SELECT MIN(message_time) as oldest_message_time
			FROM ' . PRIVMSGS_TABLE;
		$result = $db->sql_query($sql);
		$pm_stats['oldest_message_time'] = (int) $db->sql_fetchfield('oldest_message_time');
		$db->sql_freeresult($result);

		// get newest message date
		$sql = 'SELECT MAX(message_time) as newest_message_time
			FROM ' . PRIVMSGS_TABLE;
		$result = $db->sql_query($sql);
		$pm_stats['newest_message_time'] = (int) $db->sql_fetchfield('newest_message_time');
		$db->sql_freeresult($result);

		return $pm_stats;
	}

	private function get_prune_pms($prune_date, $ignore_ams = false)
	{
		global $db;

		$prune_date = explode('-', $prune_date);
		$prune_date = gmmktime(0, 0, 0, (int) $prune_date[1], (int) $prune_date[0], (int) $prune_date[2]);

		// Get private messages
		$sql = 'SELECT msg_id
			FROM ' . PRIVMSGS_TABLE . '
			WHERE message_time < ' . $prune_date;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		// build an array of PMs we want to purge
		$pms_to_purge = array();
		foreach ($row as $key => $value)
		{
			$pms_to_purge[] = $value['msg_id'];
		}

		$ignored_pms = array();
		// remove pms that should be ignored
		if ($ignore_ams)
		{
			// ignore msg_id where author is admin or mod
			$sql = 'SELECT msg_id
				FROM ' . PRIVMSGS_TO_TABLE . '
				WHERE ' . $db->sql_in_set('author_id', $ignore_ams);
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			$ignored_pm_author_ids = array();
			foreach ($row as $key => $value)
			{
				$ignored_pm_author_ids[] = $value['msg_id'];
			}

			// now do the same for user_id
			// ignore msg_id where user is admin or mod
			$sql = 'SELECT msg_id
				FROM ' . PRIVMSGS_TO_TABLE . '
				WHERE ' . $db->sql_in_set('user_id', $ignore_ams);
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			$ignored_pm_user_ids = array();
			foreach ($row as $key => $value)
			{
				$ignored_pm_user_ids[] = $value['msg_id'];
			}

			//combine the two arrays
			$ignored_pms = array_unique(array_merge($ignored_pm_author_ids, $ignored_pm_user_ids));
		}

		// now remove the msg ids from the initial array of PMs to delete
		$pms_msg_id = array_diff($pms_to_purge, $ignored_pms);

		asort($pms_msg_id);

		return $pms_msg_id;
	}

	private function delete_pms($pm_msg_ids)
	{
		global $db, $phpbb_root_path, $phpbb_container;

		// ensure our array is an array
		if (!is_array($pm_msg_ids))
		{
			$pm_msg_ids = array($pm_msg_ids);
		}

		$pm_msg_ids = array_map('intval', $pm_msg_ids);

		// first close reports
		$db->sql_query('UPDATE ' . REPORTS_TABLE . ' SET report_closed = 1 WHERE ' . $db->sql_in_set('pm_id', $pm_msg_ids));

		$db->sql_query('DELETE FROM ' . PRIVMSGS_TO_TABLE . ' WHERE ' . $db->sql_in_set('msg_id', $pm_msg_ids));

		// Check if there are any attachments we need to remove
		/** @var \phpbb\attachment\manager $attachment_manager */
		$attachment_manager = $phpbb_container->get('attachment.manager');
		$attachment_manager->delete('message', $pm_msg_ids, false);
		unset($attachment_manager);

		// delete the pms
		$db->sql_query('DELETE FROM ' . PRIVMSGS_TABLE . ' WHERE ' . $db->sql_in_set('msg_id', $pm_msg_ids));

	}

	private function get_admin_mods()
	{
		global $auth, $db;

		// Grab an array of user_id's with admin permissions
		$admin_ary = $auth->acl_get_list(false, 'a_', false);
		$admin_ary = (!empty($admin_ary[0]['a_'])) ? $admin_ary[0]['a_'] : array();

		// Grab an array of user id's with global mod permissions
		$mod_ary = $auth->acl_get_list(false,'m_', false);
		$mod_ary = (!empty($mod_ary[0]['m_'])) ? $mod_ary[0]['m_'] : array();

		// query the moderator cache table to get those who have forum mod auths
		$sql = 'SELECT user_id FROM ' . MODERATOR_CACHE_TABLE;
		$result = $db->sql_query($sql);

		$mod_f = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$mod_f[] = (int) $row['user_id'];
		}

		return array_unique(array_merge($admin_ary, $mod_ary, $mod_f));
	}
}
