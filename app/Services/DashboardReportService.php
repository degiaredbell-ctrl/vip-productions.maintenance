<?php

namespace App\Services;

use App\Enums\Period;
use App\Enums\PmStatus;
use App\Models\Machine;
use App\Models\PmRecord;

class DashboardReportService
{
    /**
     * Build yearly report split by type (machine|utility).
     * 
     * @return array{
     *  byPeriod: array<int,array{period:string,label:string,total:int,done:int,progress:int,todo:int,issue:int,percent:int,locked:bool,isCurrent:bool}>,
     *  byStatus: array{done:int,progress:int,todo:int,issue:int,total:int,percent:int},
     *  totalUnits:int
     * }
     */
    public static function build(int $year, string $type): array
    {
        $typeValue = $type === 'utility' ? \App\Enums\MachineType::Utility->value : null;

        $machinesQuery = Machine::query()->where('is_active', true);
        if ($type === 'utility') {
            $machinesQuery->where('type', $typeValue);
        } else {
            $machinesQuery->where('type', '!=', \App\Enums\MachineType::Utility->value);
        }
        $machineIds = $machinesQuery->pluck('id')->all();
        $totalUnits = count($machineIds);

        // Load PM records for this year + machines
        $records = PmRecord::with(['machine'])
            ->whereIn('machine_id', $machineIds)
            ->where('year', $year)
            ->get();

        // Build byPeriod
        $byPeriod = [];
        foreach (Period::cases() as $p) {
            $byPeriod[] = [
                'period' => $p->value,
                'label' => $p->label(),
                'total' => 0,
                'done' => 0,
                'progress' => 0,
                'todo' => 0,
                'issue' => 0,
                'percent' => 0,
                'locked' => $p->isFuture($year),
                'isCurrent' => $p === Period::current() && $year === now()->year,
            ];
        }
        $periodMap = [];
        foreach ($byPeriod as $i => $bp) {
            $periodMap[$bp['period']] = $i;
        }

        // Aggregate
        foreach ($records as $r) {
            $pid = $periodMap[$r->period] ?? null;
            if ($pid === null) continue;
            $st = $r->status instanceof \App\Enums\PmStatus ? $r->status->value : (string)$r->status;
            // count per record status
            $byPeriod[$pid][$st] = ($byPeriod[$pid][$st] ?? 0) + 1;
            $byPeriod[$pid]['total'] = ($byPeriod[$pid]['total'] ?? 0) + 1;
        }

        // Also count machines with NO record as todo for that period? but we want counts by status present; alternatively count per machine's latest? 
        // But dashboard shows records by period; to match "Belum" (todo) we need machines that should be done but no record or record todo? 
        // Better: for each (machine, period) determine effective status from records? maybe latest or the record for (machine,period,year). But record exists per save.
        // Also include units with no record as todo (not done) in total scope? But total in byPeriod above counts records only. Need total = units per period? 
        // But PM is per period: each active machine should have a PM record state per period? Or count missing as todo.
        // DashboardService counts missing as todo when building machineStatuses per period; mirror: for each period, total = number of active machines of this type that are "due" in context? Or simply total active units (each unit appears once per period conceptually). 
        // Alternatively, recompute by iterating machines and their record for that (year,period).
        // Simpler: reset total to $totalUnits per period? Or compute per machine.
        // Reset and recompute properly
        foreach ($byPeriod as $i => $_) {
            $byPeriod[$i]['total'] = 0; $byPeriod[$i]['done']=0; $byPeriod[$i]['progress']=0; $byPeriod[$i]['todo']=0; $byPeriod[$i]['issue']=0;
        }
        // build map record per (machine_id,period)
        $recMap = [];
        foreach ($records as $r) {
            $key = $r->machine_id.'|'.$r->period;
            // keep latest? or existing; usually one record
            if (!isset($recMap[$key])) $recMap[$key] = $r;
        }
        // per machine per period
        foreach ($machineIds as $mid) {
            foreach (Period::cases() as $p) {
                $pid = $periodMap[$p->value];
                $key = $mid.'|'.$p->value;
                $st = 'todo';
                if (isset($recMap[$key])) {
                    $rr = $recMap[$key];
                    $st = $rr->status instanceof \App\Enums\PmStatus ? $rr->status->value : (string)$rr->status;
                }
                $byPeriod[$pid]['total']++;
                if (isset($byPeriod[$pid][$st])) {
                    $byPeriod[$pid][$st]++;
                } else {
                    $byPeriod[$pid]['todo']++;
                }
            }
        }
        // percent
        foreach ($byPeriod as $i => $bp) {
            $t = $bp['total'];
            $byPeriod[$i]['percent'] = $t>0 ? (int)round(($bp['done']/$t)*100) : 0;
        }

        // byStatus totals (sum across periods? or current period? requirement says "x status [Selesai,...] dan y Jumlah counting setiap status" — overall/year view per type)
        $byStatus = ['done'=>0,'progress'=>0,'todo'=>0,'issue'=>0,'total'=>0,'percent'=>0];
        foreach ($machineIds as $mid) {
            // count latest state? or sum all period states? better sum states across periods (all PM records states count) OR count active units * periods? no. say "Jumlah counting setiap status" = total occurrences (records) grouped by status? Or total units in that status now? ambiguous. Use sum of all (machine,period) states = totalUnits * 6 periods? Or just totals from all periods combined: for each period occurrence, count status. 
            // Combine: for each of 6 periods per unit, one status -> total 6*units entries. Sum by status.
            foreach (Period::cases() as $p) {
                $key = $mid.'|'.$p->value;
                $st = isset($recMap[$key]) ? ($recMap[$key]->status instanceof \App\Enums\PmStatus ? $recMap[$key]->status->value : (string)$recMap[$key]->status) : 'todo';
                if (isset($byStatus[$st])) $byStatus[$st]++; else $byStatus['todo']++;
                $byStatus['total']++;
            }
        }
        $byStatus['percent'] = $byStatus['total']>0 ? (int)round(($byStatus['done']/$byStatus['total'])*100) : 0;

        return [
            'byPeriod' => $byPeriod,
            'byStatus' => $byStatus,
            'totalUnits' => $totalUnits,
        ];
    }
}
