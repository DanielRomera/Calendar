<?php

define("API_URL","destiny-aoc.net/dkp");
define("API_KEY","ca488a62b93e8089b2d613f602af5dd74b28060a22d8feebcd3b95828b081973");
define("API_KEY_TYPE","user");

/*
*
* Developed by:
* - Pompero
*
*/

$raidCalendar = new RaidCalendar($bot);

/*
The Class itself...
*/
class RaidCalendar extends BaseActiveModule
{
    /*
    Constructor:
    Hands over a referance to the "Bot" class.
    Defines access control for the commands
    Creates settings for the module
    Defines help for the commands
    */
    function __construct (&$bot)
    {
        //Initialize the base module
        parent::__construct($bot, get_class($this));

		
        //Registramos el comando
        $this->register_command("all", "calendar", "MEMBER");
		
        //ayuda
        $this -> help['command']['calendar'] = "Shows the raid calendar info";
    }
    
    function command_handler($source, $msg, $origin)
    {
        //ALWAYS reset the error handler before parsing the commands to prevent stale errors from giving false reports
        $this->error->reset();
        
        $com = $this->parse_com($msg, array('com', 'sub', 'args'));
        $who = $this -> bot -> core("whois") -> lookup($source);  

        
        switch($com['sub'])
        {
            case 'next':
                return $this -> calendar_next($source);
                break;
            case 'signup':
                return $this -> calendar_signup($source, $com['args']);
                break;
            case 'get':
                return $this -> calendar_get($source, $com['args']);
                break;
            default:
                return $this -> calendar_all($source);
                break;
        }
    }

    function calendar_signup($nick, $args)
    {
		$eventid = $args;
		$who = $this -> bot -> core("whois") -> lookup($nick);
        $roleid = $this->get_roleid($who['class']);
		$memberid = 0;
		$status=1;
		
		$ch = curl_init(API_URL.'/api.php?function=points&format=json&atoken='.API_KEY.'&type='.API_KEY_TYPE);
		// Se establece la URL y algunas opciones
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$content = curl_exec($ch);
		curl_close($ch);
		$json = json_decode($content, true);
		foreach($json["players"] as $key=>$value)
		{
			if($value["name"]==$nick)
			{
				$memberid=$value["id"];
				break;
			}
		}
		if(!$memberid)
			return "This character is not registered on the calendar, please, use you raiding character to use this command";
		
		$xml="<request><eventid>".$eventid."</eventid><memberid>".$memberid."</memberid><status>".$status."</status><role>".$roleid."</role><note>".$note."</note><raidgroup>".$raidgroup."</raidgroup></request>";
		
		$ch = curl_init(API_URL.'/api.php?function=raid_signup&format=json&atoken='.API_KEY.'&type='.API_KEY_TYPE);
		// Se establece la URL y algunas opciones
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);		

		$content = curl_exec($ch);
		curl_close($ch);
		$json = json_decode($content, true);

		if($json["status"]===0)
		{
			if($json["error"]=="no memberid given")
				echo "This bot has no permissions to sign you up";
			else
				echo "There was an error: ".$json["error"];
		}
		else if($json["status"]===1)
			return "You have been signed up";
		
		return "Unexpected error. Calendar may be down";
    }

    function calendar_get($nick, $args)
    {
		$event_id = $args;
		
		$ch = curl_init(API_URL.'/api.php?function=calevents_details&eventid='.$event_id.'&format=json&atoken='.API_KEY.'&type='.API_KEY_TYPE);
		// Se establece la URL y algunas opciones
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$content = curl_exec($ch);
		curl_close($ch);
		$json = json_decode($content, true);
		
		$output = "<font face='hyboriansmall' color='green'><center>Raid</center></font><br><br><br>";
		$output .="Signed (".$json["raidstatus"]["status0"]["count"]."/".$json["raidstatus"]["status0"]["maxcount"].")<br>";
		
		foreach($json["raidstatus"]["status0"]["categories"] as $category=>$info)
		{
			$dif = $json["raidstatus"]["status0"]["categories"][$category]["count"] - $json["raidstatus"]["status0"]["categories"][$category]["maxcount"];
			$class_count=[];
			
			if(abs($dif) == 0)
				$color="green";
			else if(abs($dif) <= 1)
				$color="yellow";
			else if(abs($dif) <= 2)
				$color="orange";
			else
				$color="red";
			if($dif > 0)
				$dif = "+".$dif;
			$ji=0;
			$output.="<font face='hyboriansmall' color='orange'>".$info['name']."</center> </font>Signed (".$json["raidstatus"]["status0"]["categories"][$category]["count"]."/".$json["raidstatus"]["status0"]["categories"][$category]["maxcount"].") Deviation <font color='".$color."'>".$dif."</font><br>";
			foreach($json["raidstatus"]["status0"]["categories"][$category]["chars"] as $key=>$value)
			{
				
				$last_seen = $this -> bot -> core("online") -> get_last_seen($value["name"], TRUE);
				
				$alter_invite ="";
				
				if(!$last_seen)
				{
					//not registered by the bot
					$who = $this -> bot -> core("whois") -> lookup($value["name"]);
					$online ="[<font color='red'>OFF</font>]";
					if($who["online"]==1)
						$online ="[<font color='green'>ON</font>]";
					if(!$who)
						$online ="[???]";					
				}
				else
				{
					$last_nick_used = $last_seen[1];
					$online_status = $this -> bot -> core("online") -> get_online_state($last_nick_used);
					
					if($online_status["status"]==1 && $value["name"]==$last_nick_used)
						$online ="[<font color='green'>ON</font>]";
					else if($online_status["status"]==1 && $value["name"]!=$last_nick_used)
					{
						$online ="[<font color='yellow'>ALTER</font>]";
						$alter_invite="/ <a href='chatcmd:///invitetoraid ".$last_nick_used."'>".$last_nick_used."</a> ";
					}
					else
						$online ="[<font color='red'>OFF</font>]";
				}
				
				$who["class"] = $this->get_user_class($value["classid"]);
				
				$output.=$online." <a href='chatcmd:///invitetoraid ".$value["name"]."'>".$value["name"]."</a> ".$alter_invite."- ".$who["class"]." - ".$value["rank"]."<br>";
				
				if($class_count[$who["class"]])
					$class_count[$who["class"]]++;
				else
					$class_count[$who["class"]]=1;
			}
			
			$output.= "Classes count: ";
			foreach($class_count as $class=>$count)
				$output.=$class.": ".$count."; ";
			$output.= "<br>";	
		}
		
		return "<a href=\"text://$output\">Information on event ".$json["title"]."</a>";
    }
	
    function calendar_all($nick)
    {
		$output = "<font face='hyboriansmall' color='orange'><center>Raids</center></font><br><br><br>";
		$ch = curl_init(API_URL.'/api.php?function=calevents_list&raids_only=1&number=10&format=json&atoken=ca488a62b93e8089b2d613f602af5dd74b28060a22d8feebcd3b95828b081973&type='.API_KEY_TYPE);
		// Se establece la URL y algunas opciones
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		$content = curl_exec($ch);
		curl_close($ch);
		$json = json_decode($content, true);


		foreach ($json["events"] as $key => $value) {
			$event_id = explode(":",$key)[1];
			$title = $value["title"];
			$start = $value["start"];
			$end = $value["end"];
			$raidleader = $value["raidleader"];
			$confirmed = $value["raidstatus"]["status0"]["count"];
			$signed = $value["raidstatus"]["status1"]["count"];
			$unsigned = $value["raidstatus"]["status2"]["count"];
			
			$output.="<font face='hyboriansmall' color='red'>Title</font>: ".$title."<br>";
			$output.="<font face='hyboriansmall' color='red'>Time</font>: ".$start." - ".$end."</font><br>";
			$output.="<font face='hyboriansmall' color='red'>Raidleader</font>: ".$raidleader."</font><br>";
			$output.="<font face='hyboriansmall' color='red'>Signup information</font>: Confirmed: <font color='green'>".$confirmed."</font> Signed out: <font color='red'>".$unsigned."</font><br>";
			$output.= $this -> bot -> core("tools") -> chatcmd("calendar get ".$event_id, "[Information]");
			$output.=" - ";
			$output.= $this -> bot -> core("tools") -> chatcmd("calendar signup ".$event_id, "[Sign UP]");
			$output.="<br>-<br>";
		}
        
		
		return "<a href=\"text://$output\">Calendar events</a>";
    }
	
	function get_roleid($class)
	{
		if($class=="Priest of Mitra" || $class=="Bear Shaman" || $class=="Tempest of Set")
			return 1;
		if($class=="Guardian" || $class=="Dark Templar" || $class=="Conqueror")
			return 2;
		if($class=="Ranger" || $class=="Demonologist" || $class=="Necromancer")
			return 3;
		if($class=="Assassin" || $class=="Barbarian" || $class=="Herald of Xotli")
			return 4;
	}

	function get_user_class($id)
	{
		switch($id)
		{
			case 1:
				return "Assasin";
			case 2:
				return "Barbarian";
			case 3:
				return "Ranger";
			case 4:
				return "Conqueror";
			case 5:
				return "Dark Templar";
			case 6:
				return "Guardian";
			case 7:
				return "Demonologist";
			case 8:
				return "Herald of Xotli";
			case 9:
				return "Necromancer";
			case 10:
				return "Bear Shaman";
			case 11:
				return "Priest of Mitra";
			case 12:
				return "Tempest of Seth";
			default:
				return "Unknown";
		}
	}
}
?>
