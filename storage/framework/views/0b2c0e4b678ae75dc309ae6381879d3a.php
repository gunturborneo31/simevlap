<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title><?php echo e($viewTitle); ?> - <?php echo e($tableLabel); ?></title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #1f2937; }
        h1, p { margin: 0; }
        .header { margin-bottom: 8px; }
        .header h1 { font-size: 14px; }
        .header p { font-size: 10px; color: #334155; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #9ca3af; padding: 4px; vertical-align: top; word-wrap: break-word; }
        thead th { background: #d1fae5; text-align: center; font-weight: 700; }
        .sub-head th { background: #ecfdf5; }
        .index-head th { background: #d1fae5; font-size: 9px; color: #065f46; }
        .center { text-align: center; }
        .right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <h1><?php echo e($viewTitle); ?> - <?php echo e($tableLabel); ?></h1>
        <p>Export <?php echo e(strtoupper($exportFormat ?? 'PDF')); ?> | Tahun <?php echo e($selectedYear ?? '-'); ?></p>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width:34px;">No</th>
                <th rowspan="2" style="width:130px;"><?php echo e($entityHeaderLabel); ?></th>
                <th rowspan="2" style="width:200px;">Program</th>
                <th rowspan="2" style="width:95px;">Pagu RPJMD (<?php echo e($selectedYear ?? 2026); ?>)</th>
                <th rowspan="2" style="width:95px;">Pagu Renstra (<?php echo e($selectedYear ?? 2026); ?>)</th>
                <th rowspan="2" style="width:95px;">Pagu RKPD/Renja (<?php echo e($selectedYear ?? 2026); ?>)</th>
                <th colspan="2">Konsistensi RPJMD - Renstra</th>
                <th colspan="2">Konsistensi RPJMD - RKPD/Renja</th>
                <th colspan="2">Konsistensi Renstra - RKPD/Renja</th>
            </tr>
            <tr class="sub-head">
                <th style="width:95px;">Selisih Anggaran</th>
                <th style="width:70px;">Status</th>
                <th style="width:95px;">Selisih Anggaran</th>
                <th style="width:70px;">Status</th>
                <th style="width:95px;">Selisih Anggaran</th>
                <th style="width:70px;">Status</th>
            </tr>
            <tr class="index-head">
                <th>(1)</th>
                <th>(2)</th>
                <th>(3)</th>
                <th>(4)</th>
                <th>(5)</th>
                <th>(6)</th>
                <th>(7)</th>
                <th>(8)</th>
                <th>(9)</th>
                <th>(10)</th>
                <th>(11)</th>
                <th>(12)</th>
            </tr>
        </thead>
        <tbody>
            <?php $__empty_1 = true; $__currentLoopData = $groups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $group): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <?php
                    $lines = $group['lines'] ?? [];
                    $lineCount = max(count($lines), 1);
                ?>

                <?php for($i = 0; $i < $lineCount; $i++): ?>
                    <?php
                        $line = $lines[$i] ?? [
                            'program_name' => '-',
                            'rpjmd_pagu' => 0,
                            'renstra_pagu' => 0,
                            'rkpd_pagu' => 0,
                            'diff_rpjmd_renstra' => 0,
                            'diff_rpjmd_rkpd' => 0,
                            'diff_renstra_rkpd' => 0,
                            'status_rpjmd_renstra' => '-',
                            'status_rpjmd_rkpd' => '-',
                            'status_renstra_rkpd' => '-',
                        ];
                    ?>
                    <tr>
                        <?php if($i === 0): ?>
                            <td rowspan="<?php echo e($lineCount); ?>" class="center"><?php echo e($group['no']); ?></td>
                            <td rowspan="<?php echo e($lineCount); ?>"><?php echo e($group['entitas']); ?></td>
                        <?php endif; ?>

                        <td><?php echo e($line['program_name']); ?></td>
                        <td class="right"><?php echo e(number_format((int) $line['rpjmd_pagu'], 0, ',', '.')); ?></td>
                        <td class="right"><?php echo e(number_format((int) $line['renstra_pagu'], 0, ',', '.')); ?></td>
                        <td class="right"><?php echo e(number_format((int) $line['rkpd_pagu'], 0, ',', '.')); ?></td>
                        <td class="right"><?php echo e(number_format((int) $line['diff_rpjmd_renstra'], 0, ',', '.')); ?></td>
                        <td class="center"><?php echo e($line['status_rpjmd_renstra']); ?></td>
                        <td class="right"><?php echo e(number_format((int) $line['diff_rpjmd_rkpd'], 0, ',', '.')); ?></td>
                        <td class="center"><?php echo e($line['status_rpjmd_rkpd']); ?></td>
                        <td class="right"><?php echo e(number_format((int) $line['diff_renstra_rkpd'], 0, ',', '.')); ?></td>
                        <td class="center"><?php echo e($line['status_renstra_rkpd']); ?></td>
                    </tr>
                <?php endfor; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="12" class="center">Tidak ada data.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php /**PATH C:\Users\kamin\Documents\Project\simevlap\resources\views/exports/resume_tabel_4.blade.php ENDPATH**/ ?>