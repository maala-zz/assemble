<?php
require "connectdb.php";
include "Maala.php";
require "priorityqueue.php";
$idx_courses = 0;
$idx_rooms = 1;
$idx_schedual = 2;
$idx_period = 3;
$idx_std_courses = 4;
$idx_std = 5;
// prepare data
$sql_courses = "SELECT * from s_course ORDER BY course_cp ASC";
$sql_rooms = "SELECT * from exams_rooms ORDER BY capacity DESC";
$sql_schedual = "SELECT * from exams_schedual";
$sql_period = "SELECT * from exam_periods";
$sql_std_courses = "SELECT * from acadimic_reg_std";
$sql_std = "SELECT * from affairs_reg_std";
$sql_arr = [$sql_courses, $sql_rooms, $sql_schedual, $sql_period, $sql_std_courses, $sql_std];
$cross_prob = mt_rand() / mt_getrandmax(); // used in GA CrossOver function
$mutation_prob = mt_rand() / mt_getrandmax(); // used in GA Mutation function
// used in implementation , here just declaring for clarity
$query_arr = [];
$course_schid = [];
$courses_ids = [];
$arr_std_courses = [];
$arr_pr = [];
$arr_sch = [];
$arr_rooms = [];
$course_cap = [];
$room_cap = [];
$RoomsCapSum = 0;
// execute the queries above
for ($i = 0; $i < count($sql_arr); $i++) {
    $q = mysqli_query($con, $sql_arr[$i]);
    $query_arr[$i] = $q;
}

// arr_std_courses[i] contains the student with id = i courses
// so fill the array as I mentioned
$query_std_courses_temp = $query_arr[$idx_std_courses];
while ($row = mysqli_fetch_assoc($query_std_courses_temp)) {
    $std_id = $row["std_id"];
    $cr_id = $row["course_id"];
    if (!isset($arr_std_courses[$std_id])) {
        $arr_std_courses[$std_id] = [];
    }
    array_push($arr_std_courses[$std_id], $cr_id);
}

// put queries in variables to make it easier to deal with it in the code
$courses = $query_arr[$idx_courses];
$scheduals = $query_arr[$idx_schedual];
$periods = $query_arr[$idx_period];
$rooms = $query_arr[$idx_rooms];

// seek cursors to the beginning of the queries (just to avoid re-query and consuming time )
mysqli_data_seek($courses, 0);
mysqli_data_seek($scheduals, 0);
mysqli_data_seek($periods, 0);
// counting
$courses_cnt = mysqli_num_rows($courses);
$periods_cnt = mysqli_num_rows($periods);
$sch_cnt = mysqli_num_rows($scheduals);

// put the scheduals IDs in arr_sch array
while ($row = mysqli_fetch_assoc($scheduals)) {
    array_push($arr_sch, $row["id"]);
}

// put the periods IDs in arr_pr array
while ($row = mysqli_fetch_assoc($periods)) {
    array_push($arr_pr, $row["id"]);
}

// put the periods IDs in arr_pr array
while ($row = mysqli_fetch_assoc($rooms)) {
    array_push($arr_rooms, $row["room_id"]);
    $room_cap[$row["room_id"]] = $row["capacity"];
    $RoomsCapSum += $room_cap[$row["room_id"]];
}

// mapping course's id to it's capacity
while ($row = mysqli_fetch_assoc($courses)) {
    $course_cap[$row["id"]] = $row["course_cp"];
    array_push($courses_ids, $row["id"]);
}

mysqli_data_seek($courses, 0);
mysqli_data_seek($scheduals, 0);
mysqli_data_seek($periods, 0);
//------------------------------------------------------- GA code

class chromo_typ
{
    public $arr_SchId = [];
    public $arr_PrId = [];
    public $fitness;
}
$population = []; // size = 10
$population_sz = 10;
// generate our initial random population
for ($temp = 1; $temp <= 10; $temp++) {
    $chrom = new chromo_typ();
    // give each course a period id and schedual id
    foreach ($courses_ids as $i) {
        $sch_id = $arr_sch[rand(0, $sch_cnt - 1)]; // random from periods IDs array
        $pr_id = $arr_pr[rand(0, $periods_cnt - 1)]; // random from scheduals IDs array
        $chrom->arr_SchId[$i] = $sch_id;
        $chrom->arr_PrId[$i] = $pr_id;
    } // end for i <= courses cnt
    $chrom->fitness = calc_fitness($chrom);
    array_push($population, $chrom);
} // end for temp <= 10

// parameters : two chromosomes , return value :  chromosome which is the result of crossover
function CrossOver($chrom1, $chrom2)
{
    global $arr_sch , $courses_ids ;
    global $courses_cnt, $cross_prob;
    foreach ($courses_ids as $i) {
        $r = mt_rand() / mt_getrandmax();
        // swap depinding on cross prob
        if ($r <= $cross_prob) {
            swap($chrom1->arr_SchId[$i], $chrom2->arr_SchId[$i]);
            swap($chrom1->arr_PrId[$i], $chrom2->arr_PrId[$i]);
        }
    }
    return $chrom1;
}
/*
parameters : chromosome to be mutate , passed by reference
returned value : NONE
 */
function Mutation(&$chrom)
{
    global $population , $arr_sch, $arr_pr, $mutation_prob , $courses_ids ;
    global $population_sz;
    $courses_cnt = count( $courses_ids ) ;
    $periods_cnt = count( $arr_pr ) ;
    $sch_cnt = count( $arr_sch ) ;
    // pick random course number, scheduale id and period id then mutate the course
    $course_num = $courses_ids[rand(0 , $courses_cnt - 1)] ;
    $sch_rand = $arr_sch[ rand(0, $sch_cnt - 1) ] ;
    $pr_rand = $arr_pr[ rand(0, $periods_cnt - 1) ] ;

    $r = mt_rand() / mt_getrandmax();
    if ($r <= $mutation_prob) {
        $chrom->arr_SchId[$course_num] = $arr_sch[$sch_rand];
        $chrom->arr_PrId[$course_num] = $arr_pr[$pr_rand];
    }
}

function calc_fitness($chrom)
{
    $fitness = 0;
    // just globaling the variables and arrays
    global $query_arr, $idx_std, $arr_std_courses, $population, $course_cap, $RoomsCapSum;
    $query_std_temp = $query_arr[$idx_std];
    mysqli_data_seek($query_std_temp, 0);
    while ($row = mysqli_fetch_assoc($query_std_temp)) {
        $std_id = $row["id"];
        $conflict = 0;
        if (!isset($arr_std_courses[$std_id])) {
            continue;
        }

        $arr_curstd_courses = $arr_std_courses[$std_id]; // array contain the current stdudent courses
        $arr_counting_temp = []; // counting conflicts
        $arr_ComputingSum = []; // Comute total sum of courses capacity grouped in the same day to avoide overflow
        foreach ($arr_curstd_courses as $e) {
            /*
            check each student's courses if there is any conflict by mapping
            period id , schedule id pairs and count them for each student in arr_counting_temp
             */
            $sch_id = $chrom->arr_SchId[$e];
            $pr_id = $chrom->arr_PrId[$e];
            $index = ((string) ($sch_id)) . '_' . ((string) ($pr_id));
            if (!isset($arr_counting_temp[$index])) {
                $arr_counting_temp[$index] = 0;
            }

            if (!isset($arr_ComputingSum[$index])) {
                $arr_ComputingSum[$index] = 0;
            }
            $arr_counting_temp[$index] += 1;
            $arr_ComputingSum[$index] += $course_cap[$e];
            // more than course in the same time !! increase conflicts counter
            if ($arr_counting_temp[$index] > 1) {
                $conflict++;
            }
            // no enough rooms for the courses !! ==> very bad fitness
            if ($arr_ComputingSum[$index] > $RoomsCapSum) {
                return -100000;
            }
        }
        $fitness -= $conflict;
    } // end fetching std temp
    return $fitness;
}

$ans = new chromo_typ();
main();

foreach ($courses_ids as $cr_id) {
    echo "course id = $cr_id , sch id = "
    . $ans->arr_SchId[$cr_id] . " pr id = " . $ans->arr_PrId[$cr_id] . "</br>";
}

echo "ans fitness = " . $ans->fitness . "</br>";
function main()
{
    global $population, $ans, $population_sz, $arr_sch, $arr_pr, $arr_std_courses;
    $GENERATIONLIMIT = 50;
    $GENERATION = 1;
    usort($population, "cmp"); // depinding on fitness
    $ans = $population[0]; // consider that population[0] is the best answer initially
    while (true) {
        $new_popoulation = []; // temp population to save the offsprings
        if ($GENERATION >= $GENERATIONLIMIT) {
            break;
        }
        $GENERATION++;

        for ($i = 0; $i < $population_sz; $i++) {
            $idx1 = $i;
            $idx2 = rand(0, $population_sz - 1);
            $OffSpring = CrossOver(clone $population[$idx1], clone $population[$idx2]);
            $OffSpring->fitness = calc_fitness($OffSpring);
            Mutation($OffSpring);
            array_push($new_popoulation, $OffSpring);
            array_push($new_popoulation, $population[$i]);
        }
        $new_population_sz = count($new_popoulation);
        usort($new_popoulation, "cmp");
        for ($i = 0; $i < $population_sz; $i++) {
            $population[$i] = $new_popoulation[$i];
            if ($population[$i]->fitness > $ans->fitness) {
                $ans = $population[$i];
            }
        }
    }
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
    print_r($e);
    echo "</br>";
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
    global $courses_cnt, $course_cap, $arr_sch, $arr_rooms, $room_cap;
    $RoomsCap = [];
    $RoomsIds = [];
    $CourseCap = [];
    $Result = [];
    foreach ($CoursesIds as $id) {
        $CourseCap[$id] = $course_cap[$id];
    }
    foreach ($arr_rooms as $room_id) {
        array_push($RoomsIds, $room_id);
    }
    foreach ($RoomsIds as $room_id) {
        $RoomsCap[$room_id] = $room_cap[$room_id];
    }
// sort them DESC
    usort($RoomsIds, "CmpRoomsCap");
    usort($CoursesIds, "CmpCoursesCap");

    $i = 0;
    $j = 0;
// divide each room in two halfs then put a course in each part
    for (; $j < count($RoomsCap); $j++) {
        $RoomId = $RoomsIds[$j];
        if ($i >= count($CoursesIds)) {
            $i = 0;
        }
        $CourseId1 = $CoursesIds[$i];
        if ($CourseCap[$CourseId1] == 0) {
            break;
        }

        $CourseId2 = -1;
// two courses left !!
        if ($i + 1 < count($CoursesIds)) {
            $CourseId2 = $CoursesIds[$i + 1];
// put a course in each half
            $half1 = min((int) ((1 + $RoomsCap[$RoomId]) / 2), $CourseCap[$CourseId1]);
            $half2 = min((int) ($RoomsCap[$RoomId] / 2), $CourseCap[$CourseId2]);
            echo "room id = $RoomId , with Cap = $RoomsCap[$RoomId] got
from Course$CourseId1 = $half1 , from Course$CourseId2 = $half2";
// subtract the result from the capacities and room size
            $RoomsCap[$RoomId] -= ($half1 + $half2);
            $CourseCap[$CourseId1] -= $half1;
            $CourseCap[$CourseId2] -= $half2;
            echo ", new cap = $RoomsCap[$RoomId] </br>";
            $i += 1 + ($half2 != 0);
        } else { // just one course left !!
            $i++;
// min between size of the room divided by two, and course capacity
            $half = min((int) ((1 + $RoomsCap[$RoomId]) / 2), $CourseCap[$CourseId1]);
            echo "room id = $RoomId , with Cap = $RoomsCap[$RoomId] got from Course$CourseId1 = $half ";
            $RoomsCap[$RoomId] -= $half;
            $CourseCap[$CourseId1] -= $half;
            echo ", new cap = $RoomsCap[$RoomId] </br>";
        }
    }
    $i = 0;
// any student left !! so shuffle them and and randomly distribute them
    shuffle($CoursesIds);
    for ($j = 0; $j < count($RoomsCap); $j++) {
        if ($i == count($CoursesIds)) {
            break;
        }

        $CourseId = $CoursesIds[$i];
        $RoomId = $RoomsIds[$j];
        if ($CourseCap[$CourseId] == 0) {
            $i++;
            continue;
        }
        if ($RoomsCap[$RoomId] == 0) {
            continue;
        }
        $half = min($RoomsCap[$RoomId], $CourseCap[$CourseId]);
        echo "ROOM id = $RoomId , with Cap = $RoomsCap[$RoomId] , got from Course$CourseId = $half
, old course = $CourseCap[$CourseId]</br>";
        $RoomsCap[$RoomId] -= $half;
        $CourseCap[$CourseId] -= $half;
        if ($CourseCap[$CourseId] == 0) {
            $i++;
        }
    }
    if ($i != count($CoursesIds)) {
        echo "what's happen now buddy!!";
    }
    echo "</br>-----------------------------------------</br>";
}
