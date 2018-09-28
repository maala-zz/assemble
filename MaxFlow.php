
<?php
/*
MaxFlow using Dinic
29-08-2018
maala.mhrez.it@hotmail.com
 */
$dinic = new Dinic();
class Edge
{
    public $cap, $to;
}
class Dinic
{
    public $adj = [], $edges = [], $level = [], $on = [];
}
function create_edge($T, $C)
{
    global $dinic;
    if (!isset($T)) {
        throw new Exception("Dinic->create_edge function : T isn't set !! </br>");
    }
    if (!isset($C)) {
        throw new Exception("Dinic->create_edge function : C isn't set !! </br>");
    }
    $E = new Edge();
    $E->to = $T;
    $E->cap = $C;
    return $E;
}

function add($from, $to, $CAP)
{
    global $dinic;
    if (!isset($dinic)) {
        throw new Exception("Dinic->add function : dinic isn't set !! </br>");
    }
    if (!isset($from)) {
        throw new Exception("Dinic->add function : from isn't set !! </br>");
    }
    if (!isset($to)) {
        throw new Exception("Dinic->add function : to isn't set !! </br>");
    }
    if (!isset($CAP)) {
        throw new Exception("Dinic->add function : CAP isn't set !! </br>");
    }
    if (!isset($dinic->adj[$from])) {
        $dinic->adj[$from] = [];
    }
    if (!isset($dinic->adj[$to])) {
        $dinic->adj[$to] = [];
    }
    array_push($dinic->adj[$from], count($dinic->edges));
    array_push($dinic->edges, create_edge($to, $CAP));
    array_push($dinic->adj[$to], count($dinic->edges));
    array_push($dinic->edges, create_edge($from, 0));
}

function bfs($sink, $src)
{
    global $dinic;
    $dinic->level = [];
    $q = new SplQueue();
    $dinic->level[$sink] = 0;
    $q->enqueue($sink);
    while (!$q->isEmpty()) {
        $cur = $q->dequeue();
        // echo "cur node = $cur </br>";
        if (!isset($cur)) {
            throw new Exception("Dinic->bfs function : cur isn't set , queue Error !!</br>");
        }
        if (!isset($dinic->adj[$cur])) {
            throw new Exception("Dinic->bfs function : dinic->adj[cur] has no nodes !!");
        }
        foreach ($dinic->adj[$cur] as $id) {
            if ($dinic->edges[$id]->cap == 0) {
                continue;
            }
            $to = $dinic->edges[$id]->to;
            if (!isset($dinic->level[$to])) {
                $dinic->level[$to] = 0;
            }
            if ($dinic->level[$to] == 0 && $to != $sink) {
                $q->enqueue($to);
                $dinic->level[$to] = $dinic->level[$cur] + 1;
            }
        }
    }
    if (!isset($dinic->level[$src])) {
        return 0;
    }
    return $dinic->level[$src] > 0;
}

function dfs($v, $src, $cur)
{
    global $dinic;
    if ($v == $src) {
        return $cur;
    }
    if (!isset($dinic->on[$v])) {
        $dinic->on[$v] = 0;
    }
    for (; $dinic->on[$v] < count($dinic->adj[$v]); $dinic->on[$v]++) {
        $id = $dinic->adj[$v][$dinic->on[$v]];
        if ($dinic->level[$v] + 1 != $dinic->level[$dinic->edges[$id]->to]) {
            continue;
        }
        if ($dinic->edges[$id]->cap > 0) {
            $temp = dfs($dinic->edges[$id]->to, $src, min($cur, $dinic->edges[$id]->cap));
            if ($temp > 0) {
                // echo "edge id = $id cap-= $temp </br>" ;
                $dinic->edges[$id]->cap -= $temp;
                $dinic->edges[$id ^ 1]->cap += $temp;
                return $temp;
            }
        }
    }
}

function max_flow($sink, $src)
{
    global $dinic;
    $ans = 0;
    while (bfs($sink, $src)) {
        $dinic->on = [];
        $cur = 0;
        while ($cur = dfs($sink, $src, 1000000000)) {
            $ans += $cur;
        }
    }
    return $ans;
}
// testing
$dinic = new Dinic(); // be careful that $dinic most be global variable
// add(from,to,cap) ;
add(0, 3, 1);
add(0, 12, 3);
add(0, 15, 3);
add(0, 19, 3);
add(0, 20, 3);

add(3 + 100, 2000, 10);
add(1 + 100, 2000, 7);
add(2 + 100, 2000, 7);
add(5 + 100, 2000, 3);
add(4 + 100, 2000, 2);

add(3, 5 + 100, 1);
add(3, 3 + 100, 1);
add(3, 4 + 100, 1);

add(12, 4 + 100, 3);
add(12, 1 + 100, 3);

add(15, 5 + 100, 3);
add(15, 4 + 100, 3);

add(19, 1 + 100, 3);
add(19, 4 + 100, 3);
add(19, 2 + 100, 3);

add(20, 3 + 100, 3);
add(20, 1 + 100, 3);
add(20, 4 + 100, 3);

echo "max flow = " . max_flow(0, 2000);
