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

	const BLOCK = 10000;

	public function main($id, $mode)
	{

		global $user, $template, $phpbb_log, $request;
		global $phpbb_root_path, $phpEx;

		if (!function_exists('validate_date'))
		{
			include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
		}

		$user->add_lang(array('acp/prune', 'memberlist'));

		$this->tpl_name = 'acp_prunepms';
		$this->page_title = 'ACP_PPMS';

		$prune = $request->is_set_post('prune');

		$prune_date = $request->variable('prune_date', '');

		$ignore_ams_switch = $request->variable('ignore_ams', 0);
		$ignore_ams = array();
		if ($ignore_ams_switch)
		{
			$ignore_ams = $this->get_admin_mods();
		}

		$error = array();

		if (!empty($prune_date) && validate_date($prune_date))
		{
			$error[] = $user->lang('PPMS_INVALID_DATE');
		}

		if ($prune && empty($error))
		{
			// private message ids that will get pruned
			$pm_msg_ids = $this->get_prune_pms($prune_date, $ignore_ams);

			if (confirm_box(true))
			{
				if (count($pm_msg_ids))
				{
					$this->delete_pms($pm_msg_ids);

					$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_PPMS_DELETED', false, array(count($pm_msg_ids)));
					$msg = $user->lang('PPMS_DELETED_SUCCESS');
				}
				else
				{
					$msg = $user->lang('PPMS_PRUNE_FAILURE');
				}

				trigger_error($msg . adm_back_link($this->u_action));
			}
			else
			{
				if (!count($pm_msg_ids))
				{
					trigger_error($user->lang('PPMS_PRUNE_FAILURE') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				$format_date = explode('-', $prune_date);
				$format_date = gmmktime(0, 0, 0, (int) $format_date[1], (int) $format_date[0], (int) $format_date[2]);
				$format_date = gmdate('M d Y', $format_date);

				$pm_count = count($pm_msg_ids);

				$template->assign_vars(array(
					'S_COUNT_PMS'			=> $pm_count,
					'S_COUNT_TOO_LARGE'		=> ($pm_count > self::BLOCK) ? true : false,
					'L_PPMS_TO_PURGE'		=> $user->lang('PPMS_TO_PURGE', count($pm_msg_ids), $format_date),
				));

				confirm_box(false, $user->lang['CONFIRM_OPERATION'], build_hidden_fields(array(
					'i'				=> $id,
					'mode'			=> $mode,
					'prune'			=> 1,

					'prune_date' 	=> $request->variable('prune_date', ''),
					'ignore_ams'	=> $request->variable('ignore_ams', 0),

				)), 'confirm_body_prunepms.html');
			}
		}

		if (empty($error))
		{
			$pm_stats = $this->get_pm_stats($ignore_ams, $prune_date);
			$pm_count = $pm_stats['pm_count'];
			$pm_oldest_time = gmdate('M d Y', $pm_stats['oldest_message_time']);
			$pm_newest_time = gmdate('M d Y', $pm_stats['newest_message_time']);

			$pms_stats_message = '';

			if ($pm_count)
			{
				if ($ignore_ams_switch)
				{
					$pms_stats_message = $user->lang('PPMS_TOTAL_STATS_NO_AMS', $pm_count, $pm_oldest_time, $pm_newest_time);
				}
				else if (!$ignore_ams_switch)
				{
					$pms_stats_message = $user->lang('PPMS_TOTAL_STATS', $pm_count, $pm_oldest_time, $pm_newest_time);
				}
			}
			else
			{
				$pms_stats_message = $user->lang('PPMS_NO_STATS', $pm_count);
			}

			if (count($pm_stats['pms_range']))
			{
				$last_element = end($pm_stats['pms_range']);
				foreach ($pm_stats['pms_range'] as $key => $value)
				{
					$pm_date = $key;
					$pm_count = $value;
					if ($value == $last_element && !empty($prune_date))
					{
						$pm_date = gmdate('M d Y', $this->format_prune_date($prune_date));
					}

					$template->assign_block_vars('pm_block', array(
						'MSG_BLOCK'	=> $user->lang('PPMS_MSG_BLOCKS', (int) $pm_count, $pm_date),
					));
				}
			}

			$template->assign_vars(array(
				'PM_COUNT'		=> $pm_count,
				'L_PPMS_TOTAL_STATS'	=> $pms_stats_message,

				'S_PPMS_STATS'	=> true,
			));
		}

		$template->assign_vars(array(
			'ERROR'			=> sizeof($error) ? implode('<br />', $error) : '',
			'PRUNE_DATE'	=> $prune_date,
			'S_SELECTED'		=> $ignore_ams_switch,
			'U_ACTION'		=> $this->u_action,
		));
	}

	/* get_pm_stats				private message statistics
	*
	* @param	$ignore_ams		array 	administrators and moderators that we ignore
	* @param	$prune_date		string 	the date to sort on
	*
	* @access	private
	* @return	array			array of private message statistics
	*/
	private function get_pm_stats($ignore_ams = array(), $prune_date = '')
	{
		global $db, $user;

		$sql_where = '';

		// ensure the parameter is an array
		if (!is_array($ignore_ams))
		{
			$ignore_ams = array($ignore_ams);
		}

		if (sizeof(array_filter($ignore_ams)))
		{
			$sql_where = ' WHERE ' . $db->sql_in_set('author_id', $ignore_ams, true);
		}

		if (!empty($prune_date))
		{
			$prune_date = $this->format_prune_date($prune_date);

			if (!empty($sql_where))
			{
				$sql_where .= ' AND message_time < ' . (int) $prune_date;
			}
			else
			{
				$sql_where = ' WHERE message_time < ' . (int) $prune_date;
			}
		}

		$pm_stats = array();

		// get total count of PMs
		$sql = 'SELECT COUNT(msg_id) as msg_id_count
			FROM ' . PRIVMSGS_TABLE . $sql_where;
		$result = $db->sql_query($sql);
		$pm_stats['pm_count'] = (int) $db->sql_fetchfield('msg_id_count');
		$db->sql_freeresult($result);

		// get oldest message date
		$sql = 'SELECT MIN(message_time) as oldest_message_time
			FROM ' . PRIVMSGS_TABLE . $sql_where;
		$result = $db->sql_query($sql);
		$pm_stats['oldest_message_time'] = (int) $db->sql_fetchfield('oldest_message_time');
		$db->sql_freeresult($result);

		// get newest message date
		$sql = 'SELECT MAX(message_time) as newest_message_time
			FROM ' . PRIVMSGS_TABLE . $sql_where;
		$result = $db->sql_query($sql);
		$pm_stats['newest_message_time'] = (int) $db->sql_fetchfield('newest_message_time');
		$db->sql_freeresult($result);

		// get date range and PM counts
		$sql = 'SELECT message_time
			FROM ' . PRIVMSGS_TABLE . $sql_where. '
			ORDER BY message_time';
		$result = $db->sql_query($sql);

		$block = self::BLOCK;

		$count = 0;
		$pm_stats['pms_range'] = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$count++;
			if ($count == $block)
			{
				$pm_stats['pms_range'][gmdate('M d Y', $row['message_time'])] = $count;
				$block = $block + self::BLOCK;
				if (($block + self::BLOCK) > $pm_stats['pm_count'])
				{
					$block = $pm_stats['pm_count'];
				}
			}
		}
		$db->sql_freeresult($result);

		return $pm_stats;
	}

	/* get_prune_pms			determine which pms to delete
	*
	* @param	$prune_date		string	the date that will determine the cutoff
	* @param	$ignore_ams		array 	administrators and moderators
	*
	* @access	private
	* @return	array			an array of msg_ids
	*/
	private function get_prune_pms($prune_date = '', $ignore_ams = array())
	{
		global $db;

		if (!empty($prune_date))
		{
			$prune_date = $this->format_prune_date($prune_date);
		}

		$db->sql_transaction('begin');

		// Get private messages
		$sql = 'SELECT msg_id
			FROM ' . PRIVMSGS_TABLE . '
			WHERE message_time < ' . (int) $prune_date;
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

			$ignored_msg_ids = array();
			foreach ($row as $key => $value)
			{
				$ignored_msg_ids[] = $value['msg_id'];
			}

			// now do the same for user_id
			// ignore msg_id where user is admin or mod
			$sql = 'SELECT msg_id
				FROM ' . PRIVMSGS_TO_TABLE . '
				WHERE ' . $db->sql_in_set('user_id', $ignore_ams);
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			foreach ($row as $key => $value)
			{
				$ignored_msg_ids[] = $value['msg_id'];
			}

			//only return unique values
			$ignored_pms = array_unique($ignored_msg_ids);
		}

		$db->sql_transaction('commit');

		// now remove the msg ids from the initial array of PMs to delete
		$pms_msg_id = array_diff($pms_to_purge, $ignored_pms);

		// sort the array
		asort($pms_msg_id);

		return $pms_msg_id;
	}

	/* delete_pms				actual deletion of the pms
	*
	* @param	$pm_msg_ids		array	msg_ids
	*
	* @access	private
	* @return	null
	*/
	private function delete_pms($pm_msg_ids)
	{
		global $db, $phpbb_root_path, $phpbb_container;

		// ensure our array is an array
		if (!is_array($pm_msg_ids))
		{
			$pm_msg_ids = array($pm_msg_ids);
		}

		$pm_msg_ids = array_map('intval', $pm_msg_ids);

		//chunk the array into smaller bites so we don't crash the server
		$array_chunk = array_chunk($pm_msg_ids, self::BLOCK);
		unset($pm_msg_ids);

		$array_count = count($array_chunk);

		$db->sql_transaction('begin');
		// can't seem to get around having queries in a loop here
		// need to chunk up the msg_id arrays as some users may have thousands of PMs they're trying to delete
		// it can't be helped if they don't heed the warnings provided
		for ($i = 0; $i < $array_count; ++$i)
		{
			$pm_msg_ids = $array_chunk[$i];

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
		$db->sql_transaction('commit');
	}

	/* get_admins_mods			returns an array of administrators and moderators
	*
	* @access	private
	* @return	array
	*/
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

		$forum_mod = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$forum_mod[] = (int) $row['user_id'];
		}
		$db->sql_freeresult($result);

		return array_unique(array_merge($admin_ary, $mod_ary, $forum_mod));
	}

	/* format_prune_date			normalize msg_id dates
	*
	* @access	private
	* @return	int
	*/
	private function format_prune_date($prune_date = '')
	{
		// from form input
		$prune_date = explode('-', $prune_date);
		$prune_date = gmmktime(0, 0, 0, (int) $prune_date[1], (int) $prune_date[0], (int) $prune_date[2]);

		return (int) $prune_date;
	}
}
