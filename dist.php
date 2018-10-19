<?php
require "connectdb.php";
include "Maala.php";
$exam_id = $_GET['exam_id'];
$idx_rooms = 0;
$idx_schedual = 1;
$idx_std = 2;
// prepare data
$sql_rooms = "SELECT * from rooms ORDER BY capacity DESC";
$sql_schedual = "SELECT * from exams_schedual where exam_id = $exam_id";
$sql_std = "SELECT distinct std_id from acadimic_reg_std where `semester_id` = " . $_SESSION['semester_id']; // from final marks not acadimic_reg_std
$sql_arr = [$sql_rooms, $sql_schedual, $sql_std];
// used in implementation , here just declaring for clarity
$query_arr = [];
$courses_ids = [];
$arr_sch = [];
$arr_rooms = [];
$course_cap = [];
$room_cap = [];
$courses_record_id = [];
// execute the queries above
for ($i = 0; $i < count($sql_arr); $i++) {
    $q = mysqli_query($con, $sql_arr[$i]);
    $query_arr[$i] = $q;
}

$query = mysqli_query($con, "SELECT distinct course_id from `acadimic_reg_std` where `semester_id` = " . $_SESSION['semester_id'] . " ");
while ($res = mysqli_fetch_assoc($query)) {
    array_push($courses_ids, $res['course_id']);
}

// put queries in variables to make it easier to deal with it in the code
$scheduals = $query_arr[$idx_schedual];
$rooms = $query_arr[$idx_rooms];

// put the scheduals IDs in arr_sch array
while ($row = mysqli_fetch_assoc($scheduals)) {
    array_push($arr_sch, $row["id"]);
}
mysqli_data_seek($scheduals, 0);

while ($row = mysqli_fetch_assoc($rooms)) {
    $room_id = $row['id'];
    array_push($arr_rooms, $room_id);
    $room_cap[$room_id] = $row["capacity"];
    $RoomsCapSum += $room_cap[$room_id];
}
// mapping course's id to it's capacity
foreach ($courses_ids as $course_id) {
    $course_cap[$course_id] = mysqli_fetch_assoc(mysqli_query($con, "SELECT count(*)from  `acadimic_reg_std` where `course_id` = $course_id "))['sumo'];
}
class chromo_typ
{
    public $arr_SchId = [];
    public $arr_PrId = [];
}
$ans = new chromo_typ();

while ($row = mysqli_fetch_assoc($scheduals)) {
    $cr_id = $row['course_id'];
    $pr_id = $row['period_id'];
    $sch_id = $row['id'];
    $ans->arr_SchId[$cr_id] = $sch_id;
    $ans->arr_PrId[$cr_id] = $pr_id;
    $courses_record_id[$cr_id] = $row['id'];
}
// -----------------------------------------------
/*
in this section we are going to pick the courses which belong to same schedule id and period id
and distrbute them in the most ideal way
 */
$arr_CoursesGroup = [];
// grouping the courses depinding on the sch id-pr id
foreach ($courses_ids as $cr) {
    $schid = $ans->arr_SchId[$cr];
    $prid = $ans->arr_PrId[$cr];
    $index = (string) $schid . "_" . (string) $prid;
    if (!isset($arr_CoursesGroup[$index])) {
        $arr_CoursesGroup[$index] = [];
    }
    array_push($arr_CoursesGroup[$index], $cr);
}

foreach ($arr_CoursesGroup as $e) {
    // print_r($e);
    // echo "</br>";
    distribute($e);
}
// comparision function , dont care about them !!
function CmpCoursesCap($id1, $id2)
{
    global $course_cap;
    if ($course_cap[$id1] < $course_cap[$id2]) {
        return 1;
    } else {
        return -1;
    }
}
function CmpRoomsCap($id1, $id2)
{
    global $room_cap;
    if ($room_cap[$id1] < $room_cap[$id2]) {
        return 1;
    } else {
        return -1;
    }
}

// distrbute each group of courses on the rooms
function distribute($CoursesIds)
{
    global $course_cap, $arr_sch, $arr_rooms, $room_cap, $ans, $courses_record_id;
    $current_sch_id;
    $current_pr_id;
    $RoomsCap = [];
    $RoomsIds = [];
    $CourseCap = [];
    $Result = [];
    foreach ($CoursesIds as $id) {
        $CourseCap[$id] = $course_cap[$id];
        $current_sch_id = $ans->SchId[$id];
        $current_pr_id = $ans->PrId[$id];
    }
    foreach ($arr_rooms as $room_id) {
        array_push($RoomsIds, $room_id);
        $RoomsCap[$room_id] = $room_cap[$room_id];
    }
// sort them DESC
    usort($RoomsIds, "CmpRoomsCap");
    usort($CoursesIds, "CmpCoursesCap");

    $i = 0;
    $j = 0;
// divide each room in two halfs then put a course in each part
    $count_RoomsCap = count($RoomsCap);
    $count_CoursesIds = count($CoursesIds);
    for (; $j < $count_RoomsCap; $j++) {
        $RoomId = $RoomsIds[$j];
        // seek to start
        if ($i >= $count_CoursesIds) {
            $i = 0;
        }
        $CourseId1 = $CoursesIds[$i];
        // no enough seats
        if ($CourseCap[$CourseId1] == 0) {
            break;
        }

        $CourseId2 = -1;
// two courses left!!
        if ($i + 1 < $count_CoursesIds) {
            $CourseId2 = $CoursesIds[$i + 1];
// put a course in each half
            $half1 = min((int) ((1 + $RoomsCap[$RoomId]) / 2), $CourseCap[$CourseId1]);
            $half2 = min((int) ($RoomsCap[$RoomId] / 2), $CourseCap[$CourseId2]);
            // echo "room id = $RoomId , with Cap = $RoomsCap[$RoomId] got from Course $CourseId1 = $half1 , from Course $CourseId2 = $half2";
// subtract the result from the capacities and room size
            $RoomsCap[$RoomId] -= ($half1 + $half2);
            $CourseCap[$CourseId1] -= $half1;
            $CourseCap[$CourseId2] -= $half2;
            // echo ", new cap = $RoomsCap[$RoomId] </br>";
            $i += 1 + ($half2 != 0);

            // just insert into dp
            $sql = "INSERT INTO `exams_rooms` ( `schedual_id` , `room_id` , `capacity` )
             VALUES ( '$courses_record_id[$CourseId1]' , '$RoomId' , '$half1' )";
            mysqli_query($sql);
            $sql = "INSERT INTO `exams_rooms` ( `schedual_id` , `room_id` , `capacity` )
             VALUES ( '$courses_record_id[$CourseId2]' , '$RoomId' , '$half2' )";
            mysqli_query($sql);

        } else { // just one course left !!
            $i++;
// min between size of the room divided by two, and course capacity
            $half = min((int) ((1 + $RoomsCap[$RoomId]) / 2), $CourseCap[$CourseId1]);
            echo "room id = $RoomId , with Cap = $RoomsCap[$RoomId] got from Course $CourseId1 = $half ";
            $RoomsCap[$RoomId] -= $half;
            $CourseCap[$CourseId1] -= $half;
            $sql = "INSERT INTO `exams_rooms` ( `schedual_id` , `room_id` , `capacity` )
             VALUES ( '$courses_record_id[$CourseId1]' , '$RoomId' , '$half' )";
            mysqli_query($sql);
            // echo ", new cap = $RoomsCap[$RoomId] </br>";
        }
    }
    ///////////////////////////////////////////////////////////////////
    $i = 0;
// any student left !! so shuffle them and and randomly distribute them
    shuffle($CoursesIds);
    for ($j = 0; $j < count($RoomsCap) && $i < $count_CoursesIds; $j++) {
        $CourseId = $CoursesIds[$i];
        $RoomId = $RoomsIds[$j];
        while ($RoomsCap[$RoomId] > 0 && $i < $count_CoursesIds) {
            $mn = min($RoomsCap[$RoomId], $CourseCap[$CourseId]);
            $RoomsCap[$RoomId] -= $mn;
            $CourseCap[$CourseId] -= $mn;
            $sql = "INSERT INTO `exams_rooms` ( `schedual_id` , `room_id` , `capacity` )
            VALUES ( '$courses_record_id[$CourseId]' , '$RoomId' , '$mn' )";
            mysqli_query($sql);
            $i++;
        }
    }
    foreach ($courses_ids as $cr_id) {
        if ($CourseCap[$cr_id] > 0) {
            echo "what's happen now buddy!!";
            break;
        }
    }
}
