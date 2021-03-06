<?php
function student_fetch($id){
	$query="SELECT * FROM student WHERE id='".$id."'";
	return db_fetch_first($query,true);
}

function student_update($student_id=NULL){
	db_query("DROP TABLE IF EXISTS view_student");
	db_query("
		CREATE TABLE view_student
		SELECT 
			student.id AS id,student.gender,student.name AS name,student.type AS type,student.id_card AS id_card,student.extra_course,
			right((1000000 + concat(student_class.class,right((100 + student_class.num_in_class),2))),6) AS num,
			class.id AS class,class.name AS class_name,class.depart AS depart,
			grade.id AS grade,grade.name AS grade_name 
		FROM 
			student 
			INNER JOIN student_class ON student.id = student_class.student
			INNER JOIN class ON student_class.class = class.id
			INNER JOIN grade ON grade.id = class.grade
		WHERE
			student_class.term = '".$_SESSION['global']['current_term']."'
		ORDER BY num
	");
	db_query("ALTER TABLE  `view_student` ADD PRIMARY KEY (  `id` )");
	db_query("ALTER TABLE  `view_student` ADD INDEX (type)");
	db_query("ALTER TABLE  `view_student` ADD INDEX (num)");
	db_query("ALTER TABLE  `view_student` ADD INDEX (class)");
	db_query("ALTER TABLE  `view_student` ADD INDEX (grade)");
	db_query("ALTER TABLE  `view_student` ADD INDEX (depart)");
	db_query("ALTER TABLE  `view_student` ADD INDEX (extra_course)");
	db_query("ALTER TABLE  `view_student` ADD FOREIGN KEY (  `id` ) REFERENCES  `starsys`.`student` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE");
}

function student_changeClass($student_id,$old_class_id,$new_class_id){
	if($old_class_id!=$new_class_id){
		$new_num_in_class=db_fetch_field("SELECT MAX(num_in_class)+1 FROM student_class WHERE class='".$new_class_id."' AND term='".$_SESSION['global']['current_term']."'");
		
		db_update('student_class',array('num_in_class'=>$new_num_in_class,'class'=>$new_class_id),"student='".$student_id."' AND class='".$old_class_id."' AND term='".$_SESSION['global']['current_term']."'");
		$new_student_num=$new_class_id.substr($new_num_in_class+100,-2);
		
		student_update($student_id);
		
		return $new_student_num;

	}else{
		return false;
	}
}

function student_addRelatives($student,$relative_data){
	$relatives=array(
		'student'=>$student,
		'name'=>$relative_data['name'],
		'relationship'=>$relative_data['relationship'],
		'contact'=>$relative_data['contact'],
		'work_for'=>$relative_data['work_for']
	);
	
	$relatives+=uidTime();
	
	return db_insert('student_relatives',$relatives);
}

function student_addBehaviour($student,$data){
	$behaviour=array(
		'student'=>$student,
		'name'=>$data['name'],
		'date'=>$data['date'],
		'type'=>$data['type'],
		'level'=>$data['level'],
		'content'=>$data['content']
	);
	
	$behaviour+=uidTime();
	
	return db_insert('student_behaviour',$behaviour);
}

function student_addComment($student,$data){
	$field=array('title','content','reply_to');
	foreach($data as $key => $value){
		if(!in_array($key,$field)){
			unset($data[$key]);
		}
	}
	
	$data['student']=$student;
	
	$data+=uidTime();
	
	return db_insert('student_comment',$data);
}

function student_deleteRelatives($student_relatives){
	$condition = db_implode($student_relatives, $glue = ' OR ','id','=',"'","'", '`','key');
	db_delete('student_relatives',$condition);
}

function student_get_scores($student){
	$query="SELECT exam_name,course_1,course_2,course_3,course_4,course_5,course_6,course_7,course_8,course_9,course_10,course_sum_3,course_sum_5,course_sum_8,rank_1,rank_2,rank_3,rank_4,rank_5,rank_6,rank_7,rank_8,rank_9,rank_10,rank_sum_3,rank_sum_5,rank_sum_8
		FROM view_score WHERE student = '".$student."'
	ORDER BY exam DESC";

	$field=array(
		'exam_name'=>array('title'=>'考试'),
		'course_1'=>array('title'=>'语文','content'=>'{course_1}<span class="rank">{rank_1}</span>'),
		'course_2'=>array('title'=>'数学','content'=>'{course_2}<span class="rank">{rank_2}</span>'),
		'course_3'=>array('title'=>'英语','content'=>'{course_3}<span class="rank">{rank_3}</span>'),
		'course_4'=>array('title'=>'物理','content'=>'{course_4}<span class="rank">{rank_4}</span>'),
		'course_5'=>array('title'=>'化学','content'=>'{course_5}<span class="rank">{rank_5}</span>'),
		'course_6'=>array('title'=>'生物','content'=>'{course_6}<span class="rank">{rank_6}</span>'),
		'course_7'=>array('title'=>'地理','content'=>'{course_7}<span class="rank">{rank_7}</span>'),
		'course_8'=>array('title'=>'历史','content'=>'{course_8}<span class="rank">{rank_8}</span>'),
		'course_9'=>array('title'=>'政治','content'=>'{course_9}<span class="rank">{rank_9}</span>'),
		'course_10'=>array('title'=>'信息','content'=>'{course_10}<span class="rank">{rank_10}</span>')
	);
	
	return fetchTableArray($query,$field);
}

function student_testClassDiv($div,$data,$classes,$gender,$showResult=false){
	global $tests,$students,$subjects;

	$tests++;
	
	$score=array();
	/*$score:array(
		1(性别)=>array(
			1(班号)=>array(
				1(科目号)=>array(
					学号=>本科分数
				)
			)
		)
	)
	*/

	//将div分班方案分解为score分数表
	for($subject=0;$subject<$subjects;$subject++){
		foreach($div as $gender_in_array1 => $array1){
			foreach($array1 as $class=>$array2){
				foreach($array2 as $student){
					$score[$gender_in_array1][$class][$subject][$student]=$data[$student][$subject];
				}
			}
		}
	}
	
	//$_SESSION['score']=$score;
	//print_r($score);
	
	$result=array();

	for($subject=0;$subject<$subjects;$subject++){
		for($class=0;$class<$classes;$class++){
			$result[$class][$subject]['num']=count($score[$gender][$class][$subject]);//得到每班每学科的人数
			$result[$class][$subject]['sum']=array_sum($score[$gender][$class][$subject]);//得到每班每学科的和
			$result[$class][$subject]['aver']=$result[$class][$subject]['sum']/$result[$class][$subject]['num'];//得到每班每学科的平均值
			//$result[$class][$subject]['std']=std($score[$gender][$class][$subject],$result[$class][$subject]['aver']);//得到每班每学科的标准差
		}
	}
	
	if($showResult){
		echo "\n<br>result".$gender.": "; print_r($result);
	}
	
	/*for($subject=0;$subject<$subjects;$subject++){
		for($class=0;$class<$classes;$class++){
	
			$std[]=$result[$class][$subject]['std'];
	
		}
	}
	
	$std_sum=array_sum($std);//各班各学科的标准差的和*/
	
	$aver_std=array();

	for($subject=0;$subject<$subjects;$subject++){

		$aver=array();

		for($class=0;$class<$classes;$class++){
			$aver[]=$result[$class][$subject]['aver'];
		}
		$aver_std[]=std($aver);
	}
	
	$aver_std_sum=array_sum($aver_std);//各班每学科总分的标差的和
	
	return $aver_std_sum;
}

function student_getIdByParentUid($parent_uid){
	return db_fetch_field("SELECT id FROM student WHERE parent = '".$parent_uid."'");
}