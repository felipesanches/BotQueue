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

	class Job extends Model
	{
		public function __construct($id = null)
		{
			parent::__construct($id, "jobs");
		}
		
		public function getName()
		{
			return basename($this->get('name'));	
		}

		public function getUser()
		{
			return new User($this->get('user_id'));
		}
		
		public function getUrl()
		{
			return "/job:" . $this->id;
		}
		
		public function getStatusHTML()
		{
			return "<span class=\"label " . self::getStatusHTMLClass($this->get('status')) . "\">" . $this->get('status') . "</span>";
		}
		
		public static function getStatusHTMLClass($status)
		{
			$s2c = array(
				'taken' => 'label-info',
				'qa' => 'label-warning',
				'complete' => 'label-success',
				'failure' => 'label-important'
			);
			
			return $s2c[$status];
		}
		
		public function getFile()
		{
			return new S3File($this->get('file_id'));
		}		

		public function getQueue()
		{
			return new Queue($this->get('queue_id'));
		}		

		public function getBot()
		{
			return new Bot($this->get('bot_id'));
		}
		
		public function getAPIData()
		{
			$d = array();
			$d['id'] = $this->id;
			$d['name'] = $this->getName();
			$d['queue'] = $this->get('queue_id');
			$d['file'] = $this->getFile()->getAPIData();
			$d['status'] = $this->get('status');
			$d['created_time'] = $this->get('created_time');
			$d['taken_time'] = $this->get('taken_time');
			$d['downloaded_time'] = $this->get('downloaded_time');
			$d['finished_time'] = $this->get('finished_time');
			$d['verified_time'] = $this->get('verified_time');
			$d['progress'] = $this->get('progress');
			
			return $d;
		}
		
		public function cancelJob()
		{
			$bot = $job->getBot();
			if ($bot->isHydrated())
			{
				$bot->set('job_id', 0);
				$bot->set('status', 'idle');
				$bot->save();
			}
			
			$this->set('status', 'cancelled');
			$this->set('bot_id', 0);
			$this->set('start', 0);
			$this->save();
		}
		
		public function getElapsedTime()
		{
			if ($this->get('status') == 'available')
			{
				$start = strtotime($this->get('created_time'));
				$end = time();
			}
			elseif ($this->get('status') == 'taken' || $this->get('status') == 'downloading')
			{
				$start = strtotime($this->get('taken_time'));
				$end = time();
			}
			elseif ($this->get('status') == 'qa')
			{
				$start = strtotime($this->get('finished_time'));
				$end = time();			  
			}
			else
			{
				$start = strtotime($this->get('created_time'));
				$end = strtotime($this->get('verified_time'));
			}
			
			return $end - $start;			
		}
		
		public function getElapsedText()
		{
			return Utility::getElapsed($this->getElapsedTime());
		}

		public function getEstimatedTime()
		{
			//okay, now estimate it for us.
			$elapsed = $this->getElapsedTime();
			if ($this->get('progress') > 0)
			{
				$total = (100 / $this->get('progress')) * $elapsed;
				return $total - $elapsed;
			}
			
			return 0;
		}

		public function getEstimatedText()
		{
			return Utility::getElapsed($this->getEstimatedTime());
		}
	}
?>