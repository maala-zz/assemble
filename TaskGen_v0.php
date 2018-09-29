<?php
require "connectdb.php";
include "Maala.php";
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
$arr_std_courses = [];
$arr_pr = [];
$arr_sch = [];
$course_cap = [];
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
    if( !isset($arr_std_courses[$std_id]) ) $arr_std_courses[$std_id] = [] ;
    array_push($arr_std_courses[$std_id], $cr_id);
}

// put queries in variables to make it easier to deal with it in the code  
$courses = $query_arr[$idx_courses];
$scheduals = $query_arr[$idx_schedual];
$periods = $query_arr[$idx_period];

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
// mapping course's id to it's capacity
while ($row = mysqli_fetch_assoc($courses)) {
    $course_cap[$row["id"]] = $row["course_cp"];
}
//------------------------------------------------------- GA code

class chromo_typ
{
    public $arr_SchId = [];
    public $arr_PrId = [];
    public $fitness;
}
$population = []; // size = 10 
$population_sz = 10 ;
// generate our initial random population
for ($temp = 1; $temp <= 10; $temp++) {
    $chrom = new chromo_typ();
    // give each course a period id and schedual id
    for ($i = 1; $i <= $courses_cnt; $i++) {
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
    global $courses_cnt, $cross_prob;
    for ($i = 1; $i <= $courses_cnt; $i++) {
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
    global $population, $courses_cnt, $arr_sch, $arr_pr, $sch_cnt, $periods_cnt, $mutation_prob;
    global $population_sz;
    // pick random course number, scheduale id and period id then mutate the course
    $course_num = rand(1, $courses_cnt);
    $sch_rand = rand(0, $sch_cnt - 1);
    $pr_rand = rand(0, $periods_cnt - 1);
    $r = mt_rand() / mt_getrandmax();
    if ($r <= $mutation_prob) {
        $chrom->arr_SchId[$course_num] = $arr_sch[$sch_rand];
        $chrom->arr_PrId[$course_num] = $arr_pr[$pr_rand];
    }
}

function calc_fitness($chrom)
{
    $fitness = 0;
    global $query_arr, $idx_std, $arr_std_courses, $population;
    $query_std_temp = $query_arr[$idx_std];
    mysqli_data_seek($query_std_temp, 0);
    while ($row = mysqli_fetch_assoc($query_std_temp)) {
        $std_id = $row["id"];
        $conflict = 0;
        $arr_curstd_courses = $arr_std_courses[$std_id]; // array contain the current stdudent courses
        $arr_counting_temp = []; // counting conflicts
        foreach ($arr_curstd_courses as $e) {
            $sch_id = $chrom->arr_SchId[$e];
            $pr_id = $chrom->arr_PrId[$e];
            $index = ((string) ($sch_id)) . '_' . ((string) ($pr_id));
            $arr_counting_temp[$index] = 0;
        }
        foreach ($arr_curstd_courses as $e) {
            $sch_id = $chrom->arr_SchId[$e];
            $pr_id = $chrom->arr_PrId[$e];
            $index = ((string) ($sch_id)) . '_' . ((string) ($pr_id));
            $arr_counting_temp[$index] += 1;
            if ($arr_counting_temp[$index] > 1) {
                $conflict++;
            }
        }
        $fitness -= $conflict;
    } // end fetching std temp
    return $fitness;
}

$ans = new chromo_typ();
function main()
{
    global $population, $ans, $population_sz, $arr_sch, $arr_pr, $arr_std_courses;
    $GENERATIONLIMIT = 50;
    $GENERATION = 1;
    usort($population , "cmp") ; // depinding on fitness
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