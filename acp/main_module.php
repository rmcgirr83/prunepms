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

	// constant used for setting chunks on the array of msg ids
	const BLOCK = 10000;

	public function main($id, $mode)
	{

		global $user, $language, $template, $phpbb_log, $request;
		global $phpbb_root_path, $phpEx;

		if (!function_exists('validate_date'))
		{
			include($phpbb_root_path . 'includes/functions_user.' . $phpEx);
		}

		$language->add_lang(array('acp/prune', 'memberlist'));

		$this->tpl_name = 'acp_prunepms';
		$this->page_title = 'ACP_PPMS';

		$prune = $request->variable('prune', false);

		$cancel = $request->variable('cancel', false);

		if ($cancel)
		{
			$prune = false;
		}

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
			$error[] = $language->lang('PPMS_INVALID_DATE');
			$prune = false;
		}

			// private message ids that will get pruned
			$pm_msg_ids = $this->get_prune_pms($prune_date, $ignore_ams);

		if ($prune)
		{
			if (confirm_box(true))
			{
				if (count($pm_msg_ids))
				{
					$this->delete_pms($pm_msg_ids);

					$phpbb_log->add('admin', $user->data['user_id'], $user->ip, 'LOG_PPMS_DELETED', false, array(count($pm_msg_ids), $user->format_date(time())));
					$msg = $language->lang('PPMS_DELETED_SUCCESS');
				}
				else
				{
					$msg = $language->lang('PPMS_PRUNE_FAILURE');
				}

				trigger_error($msg . adm_back_link($this->u_action));
			}
			else
			{
				if (!count($pm_msg_ids))
				{
					trigger_error($language->lang('PPMS_PRUNE_FAILURE') . adm_back_link($this->u_action), E_USER_WARNING);
				}

				if (!empty($prune_date))
				{
					$format_date = $this->format_prune_date($prune_date);
					$format_date = $this->format_to_gmdate($format_date);
				}
				else
				{
					$format_date = $this->format_to_gmdate(time());
				}

				$pm_count = count($pm_msg_ids);

				$template->assign_vars(array(
					'S_COUNT_PMS'			=> $pm_count,
					'S_COUNT_TOO_LARGE'		=> ($pm_count > self::BLOCK) ? true : false,
					'L_PPMS_TO_PURGE'		=> $language->lang('PPMS_TO_PURGE', count($pm_msg_ids), $format_date),
				));

				confirm_box(false, $language->lang('CONFIRM_OPERATION'), build_hidden_fields(array(
					'i'				=> $id,
					'mode'			=> $mode,
					'prune'			=> $prune,

					'prune_date' 	=> $request->variable('prune_date', ''),
					'ignore_ams'	=> $request->variable('ignore_ams', 0),

				)), 'confirm_body_prunepms.html');
			}
		}

		$pm_msg_ids_count = count($pm_msg_ids);
		if (!$pm_msg_ids_count)
		{
			if ($ignore_ams_switch)
			{
				$pms_stats_message = $language->lang('PPMS_NO_STATS_NO_AMS', $pm_msg_ids_count, (empty($prune_date) ? $this->format_to_gmdate(time()) : ''));
			}
			else
			{
				$pms_stats_message = $language->lang('PPMS_NO_STATS', $pm_msg_ids_count, (empty($prune_date) ? $this->format_to_gmdate(time()) : ''));
			}
		}

		if (empty($error) && $pm_msg_ids_count)
		{
			$pm_stats = $this->get_pm_stats($pm_msg_ids);
			$pm_count = $pm_stats['pm_count'];
			$pm_oldest_time = (isset($pm_stats['oldest_message_time'])) ? $this->format_to_gmdate($pm_stats['oldest_message_time']) : '';
			$pm_newest_time = (isset($pm_stats['newest_message_time'])) ? $this->format_to_gmdate($pm_stats['newest_message_time']) : '';

			if ($pm_count)
			{
				$pm_times_set = (!empty($pm_oldest_time) && !empty($pm_newest_time)) ? true : false;
				if ($ignore_ams_switch && $pm_times_set)
				{
					$pms_stats_message = $language->lang('PPMS_TOTAL_STATS_NO_AMS', $pm_count, $pm_oldest_time, $pm_newest_time);
				}
				else if (!$ignore_ams_switch && $pm_times_set)
				{
					$pms_stats_message = $language->lang('PPMS_TOTAL_STATS', $pm_count, $pm_oldest_time, $pm_newest_time);
				}
			}

			if (count($pm_stats['pms_range']))
			{
				$last_element = end($pm_stats['pms_range']);
				foreach ($pm_stats['pms_range'] as $key => $value)
				{
					$pm_date = $this->format_to_gmdate($key);
					$pm_count = $value;
					if ($value == $last_element && !empty($prune_date))
					{
						$pm_date = $this->format_to_gmdate($this->format_prune_date($prune_date));
					}

					$template->assign_block_vars('pm_block', array(
						'MSG_BLOCK'	=> $language->lang('PPMS_MSG_BLOCKS', (int) $pm_count, $pm_date),
					));
				}
			}
		}

		$template->assign_vars(array(
			'ERROR'			=> sizeof($error) ? implode('<br />', $error) : '',
			'PRUNE_DATE'	=> $prune_date,
			'L_PPMS_TOTAL_STATS'	=> (isset($pms_stats_message)) ? $pms_stats_message : false,

			'S_PPMS_STATS'	=> (!empty($pms_stats_message) && !$error) ? true : false,
			'S_PPMS_COUNT'	=> $pm_msg_ids_count,
			'S_SELECTED'		=> $ignore_ams_switch,
			'U_ACTION'		=> $this->u_action,
		));
	}

	/*
	* private message statistics
	*
	* @param	array	$pm_msg_ids		private message ids
	*
	* @access	private
	* @return	array			array of private message statistics
	*/
	private function get_pm_stats($pm_msg_ids = array())
	{
		global $db;

		$pm_stats = array();

		$pm_stats['pm_count'] = count($pm_msg_ids);

		// get date range and PM counts
		$sql = 'SELECT message_time
			FROM ' . PRIVMSGS_TABLE . '
			WHERE ' . $db->sql_in_set('msg_id', $pm_msg_ids);
		$result = $db->sql_query($sql);
		if (!($row = $db->sql_fetchrow($result)))
		{
			$db->sql_freeresult($result);
			return 'NO_USERS';
		}

		$block = self::BLOCK;
		$count = 0;

		$pm_stats['pms_range'] = $pm_date_range = array();
		do
		{
			$pm_date_range[] = (int) $row['message_time'];
			$count++;
			if ($count == $block)
			{
				$pm_stats['pms_range'][$row['message_time']] = $count;
				$block = $block + self::BLOCK;
				if (($block + self::BLOCK) > $pm_stats['pm_count'])
				{
					$block = $pm_stats['pm_count'];
				}
			}
		}
		while ($row = $db->sql_fetchrow($result));
		$db->sql_freeresult($result);

		if (count($pm_date_range))
		{
			$pm_stats['oldest_message_time'] = min(array_values($pm_date_range));
			$pm_stats['newest_message_time'] = max(array_values($pm_date_range));
		}

		return $pm_stats;
	}

	/*
	* determine which pms to delete
	*
	* @param	string	$prune_date		the date that will determine the cutoff
	* @param	array	$ignore_ams		administrators and moderators
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

		// if prune_date is empty use current time
		if (empty($prune_date))
		{
			$prune_date = time();
		}

		$sql_and = '';
		if (count($ignore_ams))
		{
			$sql_and = ' AND ' . $db->sql_in_set('author_id', $ignore_ams, true);
		}

		$db->sql_transaction('begin');

		// Get private messages
		$sql = 'SELECT msg_id
			FROM ' . PRIVMSGS_TABLE . '
			WHERE message_time < ' . (int) $prune_date . $sql_and;
		$result = $db->sql_query($sql);
		$row = $db->sql_fetchrowset($result);
		$db->sql_freeresult($result);

		// build an array of PMs we want to purge
		$pms_to_purge = array();
		foreach ($row as $key => $value)
		{
			$pms_to_purge[] = $value['msg_id'];
		}

		//free up some memory
		unset($row);

		$ignored_msg_ids = array();
		// remove pms that should be ignored
		if ($ignore_ams)
		{
			// ignore msg_id where author is admin or mod
			$sql = 'SELECT msg_id
				FROM ' . PRIVMSGS_TO_TABLE . '
				WHERE ' . $db->sql_in_set('author_id', $ignore_ams) . ' OR ' . $db->sql_in_set('user_id', $ignore_ams);
			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrowset($result);
			$db->sql_freeresult($result);

			$ignored_msg_ids = array();
			foreach ($row as $key => $value)
			{
				$ignored_msg_ids[] = $value['msg_id'];
			}

			//free up some memory
			unset($row);

			//only return unique values
			$ignored_msg_ids = array_unique($ignored_msg_ids);
			asort($ignored_msg_ids);
		}

		$db->sql_transaction('commit');

		// now remove the msg ids from the initial array of PMs to delete
		$pm_msg_ids = array_diff($pms_to_purge, $ignored_msg_ids);

		// sort the array
		asort($pm_msg_ids);

		return $pm_msg_ids;
	}

	/*
	* actual deletion of the pms
	*
	* @param	array	$pm_msg_ids		msg_ids
	*
	* @access	private
	* @return	void
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

	/*
	* get_admins_mods		returns an array of administrators and moderators
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

	/*
	* format_prune_date		normalize dates to integers
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


	/*
	* format_to_gmdate		normalize dates to strings
	*
	* @access	private
	* @return	string
	*/
	private function format_to_gmdate($date)
	{
		$date = (int) $date;
		$date = gmdate('M d Y', $date);

		return (string) $date;
	}
}
