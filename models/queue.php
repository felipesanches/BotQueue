<?
  /*
    This file is part of BotQueue.

    BotQueue is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    BotQueue is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with BotQueue.  If not, see <http://www.gnu.org/licenses/>.
  */

	class Queue extends Model
	{
		public function __construct($id = null)
		{
			parent::__construct($id, "queues");
		}
		
		public function getAPIData()
		{
			$d = array();
			$d['id'] = $this->id;
			$d['name'] = $this->getName();
			
			return $d;
		}
			
		public function canAdd()
		{
			return $this->isMine();
		}
		
		public function isMine()
		{
			return (User::$me->id == $this->get('user_id'));
		}	

		public function getName()
		{
			return $this->get('name');
		}

		public function getUser()
		{
			return new User($this->get('user_id'));
		}
		
		public function getUrl()
		{
			return "/queue:" . $this->id;
		}
		
		public function getJobs($status = null, $sortField = 'user_sort', $sortOrder = 'ASC')
		{
			if ($status !== null)
				$statusSql = " AND status = '{$status}'";
				
			$sql = "
				SELECT id
				FROM jobs
				WHERE queue_id = '{$this->id}'
					{$statusSql}
				ORDER BY {$sortField} {$sortOrder}
			";
			return new Collection($sql, array('Job' => 'id'));
		}
		
		public function getActiveJobs($sortField = 'user_sort', $sortOrder = 'ASC')
		{
			$sql = "
				SELECT id
				FROM jobs
				WHERE queue_id = '{$this->id}'
					AND status IN ('available', 'taken')
				ORDER BY {$sortField} {$sortOrder}
			";
			return new Collection($sql, array('Job' => 'id'));			
		}
		
		public function getBots()
		{
		  $sql = "
		    SELECT id
		    FROM bots
		    WHERE queue_id = '{$this->id}'
		    ORDER BY last_seen DESC
		  ";
		  
		  return new Collection($sql, array('Bot' => 'id'));
		}
		
		public function addGCodeFile($file, $qty = 1)
		{
			$jobs = array();
			
			for ($i=0; $i<$qty; $i++)
			{
				$sort = db()->getValue("SELECT max(id)+1 FROM jobs");
				
				$job = new Job();
				$job->set('user_id', User::$me->id);
				$job->set('queue_id', $this->id);
				$job->set('file_id', $file->id);
				$job->set('name', $file->get('path'));
				$job->set('status', 'available');
				$job->set('created_time', date("Y-m-d H:i:s"));
				$job->set('user_sort', $sort);
				$job->save();

				$jobs[] = $job;
			}
			
			return $jobs;
		}
		
		public function getStats()
		{
			$sql = "
				SELECT status, count(status) as cnt
				FROM jobs
				WHERE queue_id = {$this->id}
				GROUP BY status
			";

			$data = array();
			$stats = db()->getArray($sql);
			if (!empty($stats))
			{
				//load up our stats
				foreach ($stats AS $row)
				{
					$data[$row['status']] = $row['cnt'];
					$data['total'] += $row['cnt'];
				}
				
				//calculate percentages
				foreach ($stats AS $row)
					$data[$row['status'] . '_pct'] = ($row['cnt'] / $data['total']) * 100;
			}
			
			//pull in our time based stats.
			$sql = "
				SELECT sum(taken_time - created_time) as wait, sum(finished_time - taken_time) as runtime, sum(verified_time - created_time) as total
				FROM jobs
				WHERE status = 'complete'
					AND queue_id = {$this->id}
			";

			$stats = db()->getArray($sql);
			
			$data['total_waittime'] = (int)$stats[0]['wait'];
			$data['total_runtime'] = (int)$stats[0]['runtime'];
			$data['total_time'] = (int)$stats[0]['total'];
			$data['avg_waittime'] = $stats[0]['wait'] / $data['total'];
			$data['avg_runtime'] = $stats[0]['runtime'] / $data['total'];
			$data['avg_time'] = $stats[0]['total'] / $data['total'];

			return $data;
		}
	}
?>
