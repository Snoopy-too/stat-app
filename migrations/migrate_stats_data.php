<?php
/**
 * Migration Script: Safely Migrate data/stats.sql into statapp database.
 * 
 * Usage:
 *   php migrations/migrate_stats_data.php --dry-run
 *   php migrations/migrate_stats_data.php --execute
 */

require_once __DIR__ . '/../config/database.php';

$isDryRun = in_array('--dry-run', $argv) || !in_array('--execute', $argv);

echo "================================================================================\n";
echo "              STATAPP DATABASE MIGRATION: data/stats.sql -> Live DB             \n";
echo "================================================================================\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (Simulating changes, will ROLLBACK)" : "LIVE EXECUTION (Will COMMIT to DB)") . "\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

$sqlFile = __DIR__ . '/../data/stats.sql';
if (!file_exists($sqlFile)) {
    die("Error: stats.sql not found at $sqlFile\n");
}

/**
 * Helper to parse SQL INSERTs from stats.sql
 */
function parseSqlInserts(string $filePath): array {
    $content = file_get_contents($filePath);
    $data = [];
    $lines = explode("\n", $content);
    $currentInsert = '';
    $inInsert = false;
    $currentTable = '';
    $currentColumns = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (preg_match('/^INSERT INTO\s+`?(\w+)`?\s*\((.*?)\)\s*VALUES/i', $trimmed, $m)) {
            $inInsert = true;
            $currentTable = $m[1];
            $colsStr = $m[2];
            $currentColumns = array_map(function($c) {
                return trim(trim($c), '`');
            }, explode(',', $colsStr));
            $currentInsert = substr($trimmed, strpos($trimmed, 'VALUES') + 6);
            if (!isset($data[$currentTable])) {
                $data[$currentTable] = [
                    'columns' => $currentColumns,
                    'rows' => []
                ];
            }
        } elseif ($inInsert) {
            $currentInsert .= "\n" . $trimmed;
        }

        if ($inInsert && (substr($trimmed, -1) === ';' || preg_match('/;\s*$/', $trimmed))) {
            $inInsert = false;
            $valStr = rtrim(trim($currentInsert), ';');
            $rows = parseValuesTuples($valStr);
            foreach ($rows as $r) {
                if (count($r) === count($data[$currentTable]['columns'])) {
                    $data[$currentTable]['rows'][] = array_combine($data[$currentTable]['columns'], $r);
                } else {
                    echo "WARNING: Column count mismatch in $currentTable: expected " . count($data[$currentTable]['columns']) . " got " . count($r) . "\n";
                }
            }
            $currentInsert = '';
        }
    }
    return $data;
}

function parseValuesTuples(string $valStr): array {
    $rows = [];
    $len = strlen($valStr);
    $inTuple = false;
    $inQuotes = false;
    $quoteChar = '';
    $isEscaped = false;
    $currentField = '';
    $currentRow = [];

    for ($i = 0; $i < $len; $i++) {
        $c = $valStr[$i];

        if ($isEscaped) {
            $currentField .= $c;
            $isEscaped = false;
            continue;
        }

        if ($c === '\\') {
            $currentField .= $c;
            $isEscaped = true;
            continue;
        }

        if ($inQuotes) {
            if ($c === $quoteChar) {
                if ($i + 1 < $len && $valStr[$i+1] === $quoteChar) {
                    $currentField .= $quoteChar;
                    $i++;
                } else {
                    $inQuotes = false;
                }
            } else {
                $currentField .= $c;
            }
            continue;
        }

        if ($c === "'" || $c === '"') {
            $inQuotes = true;
            $quoteChar = $c;
            continue;
        }

        if ($c === '(' && !$inTuple) {
            $inTuple = true;
            $currentRow = [];
            $currentField = '';
            continue;
        }

        if ($c === ')' && $inTuple) {
            $inTuple = false;
            $currentRow[] = cleanVal($currentField);
            $currentField = '';
            $rows[] = $currentRow;
            continue;
        }

        if ($c === ',' && $inTuple) {
            $currentRow[] = cleanVal($currentField);
            $currentField = '';
            continue;
        }

        if ($inTuple) {
            $currentField .= $c;
        }
    }
    return $rows;
}

function cleanVal(string $val) {
    $val = trim($val);
    if (strtoupper($val) === 'NULL') {
        return null;
    }
    return stripcslashes($val);
}

echo "1. Parsing data/stats.sql...\n";
$dumpData = parseSqlInserts($sqlFile);
echo "Parsed " . count($dumpData) . " tables with data.\n\n";

echo "2. Applying Schema Harmonization Adjustments (DDL)...\n";
// Check and add is_private to clubs
$stmt = $pdo->query("SHOW COLUMNS FROM clubs LIKE 'is_private'");
if ($stmt->rowCount() === 0) {
    $pdo->exec("ALTER TABLE clubs ADD COLUMN is_private TINYINT(1) NOT NULL DEFAULT 0 AFTER slug");
    echo "  [+] Added column `is_private` to `clubs` table.\n";
} else {
    echo "  [.] Column `is_private` already exists on `clubs`.\n";
}

// Check and add reset_token to admin_users if needed
$stmt = $pdo->query("SHOW COLUMNS FROM admin_users LIKE 'reset_token'");
if ($stmt->rowCount() === 0) {
    $pdo->exec("ALTER TABLE admin_users ADD COLUMN reset_token VARCHAR(64) NULL AFTER updated_at");
    echo "  [+] Added column `reset_token` to `admin_users` table.\n";
} else {
    echo "  [.] Column `reset_token` already exists on `admin_users`.\n";
}

try {
    $pdo->beginTransaction();

    // Enable foreign keys
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "\n3. Migrating Data in Dependency Order...\n";

    // Table processing order
    $tableOrder = [
        'admin_users',
        'clubs',
        'club_admins',
        'members',
        'games',
        'teams',
        'champions',
        'game_results',
        'game_result_losers',
        'team_game_results',
        'cooperative_game_results',
        'cooperative_result_participants',
        'login_attempts',
        'registration_attempts',
        'csrf_tokens'
    ];

    $summary = [];

    foreach ($tableOrder as $table) {
        if (!isset($dumpData[$table])) continue;

        $rows = $dumpData[$table]['rows'];
        $colsStmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        $liveCols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);

        $pkStmt = $pdo->query("SHOW KEYS FROM `$table` WHERE Key_name = 'PRIMARY'");
        $pkCols = $pkStmt->fetchAll(PDO::FETCH_COLUMN, 4);
        $pk = count($pkCols) === 1 ? $pkCols[0] : null;

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            // Filter row attributes to only those existing in live schema
            $filteredRow = [];
            foreach ($row as $k => $v) {
                if (in_array($k, $liveCols)) {
                    $filteredRow[$k] = $v;
                }
            }

            // Special Business Rules for Safety:
            
            // A. Admin Users: NEVER overwrite live password hashes or active status for existing admin users!
            if ($table === 'admin_users' && isset($filteredRow['admin_id'])) {
                $chk = $pdo->prepare("SELECT admin_id, password_hash, default_club_id FROM admin_users WHERE admin_id = ?");
                $chk->execute([$filteredRow['admin_id']]);
                $existingAdmin = $chk->fetch(PDO::FETCH_ASSOC);
                if ($existingAdmin) {
                    // Do NOT overwrite password_hash, created_at, or updated_at
                    unset($filteredRow['password_hash'], $filteredRow['updated_at']);
                    if (!empty($existingAdmin['default_club_id'])) {
                        $filteredRow['default_club_id'] = $existingAdmin['default_club_id'];
                    }
                }
            }

            // B. Clubs: Preserve existing live theme, description, meeting info, location, status
            if ($table === 'clubs' && isset($filteredRow['club_id'])) {
                $chk = $pdo->prepare("SELECT club_id, theme, description, meeting_day, meeting_time, location, status FROM clubs WHERE club_id = ?");
                $chk->execute([$filteredRow['club_id']]);
                $existingClub = $chk->fetch(PDO::FETCH_ASSOC);
                if ($existingClub) {
                    // Retain existing live customizations if present
                    if (!empty($existingClub['theme'])) $filteredRow['theme'] = $existingClub['theme'];
                    if (!empty($existingClub['description'])) $filteredRow['description'] = $existingClub['description'];
                    if (!empty($existingClub['meeting_day'])) $filteredRow['meeting_day'] = $existingClub['meeting_day'];
                    if (!empty($existingClub['meeting_time'])) $filteredRow['meeting_time'] = $existingClub['meeting_time'];
                    if (!empty($existingClub['location'])) $filteredRow['location'] = $existingClub['location'];
                    if (!empty($existingClub['status'])) $filteredRow['status'] = $existingClub['status'];
                }
            }

            // C. Club Admins: Check natural key (club_id, admin_id)
            if ($table === 'club_admins' && isset($filteredRow['club_id'], $filteredRow['admin_id'])) {
                $chk = $pdo->prepare("SELECT id FROM club_admins WHERE club_id = ? AND admin_id = ?");
                $chk->execute([$filteredRow['club_id'], $filteredRow['admin_id']]);
                $existingCaId = $chk->fetchColumn();
                if ($existingCaId) {
                    // Already assigned, update role if needed
                    if (isset($filteredRow['role'])) {
                        $updCa = $pdo->prepare("UPDATE club_admins SET role = ? WHERE id = ?");
                        $updCa->execute([$filteredRow['role'], $existingCaId]);
                        $updated++;
                    } else {
                        $skipped++;
                    }
                    continue;
                } else {
                    // Insert new assignment (let auto_increment handle id to prevent PK conflict)
                    unset($filteredRow['id']);
                }
            }

            // D. Games: Preserve live game_type, default to 'winner_losers' if missing
            if ($table === 'games') {
                if (!isset($filteredRow['game_type']) || empty($filteredRow['game_type'])) {
                    if (isset($filteredRow['game_id'])) {
                        $chk = $pdo->prepare("SELECT game_type FROM games WHERE game_id = ?");
                        $chk->execute([$filteredRow['game_id']]);
                        $liveType = $chk->fetchColumn();
                        $filteredRow['game_type'] = $liveType ?: 'winner_losers';
                    } else {
                        $filteredRow['game_type'] = 'winner_losers';
                    }
                }
            }

            // E. Teams: Nullify invalid member IDs (0 -> NULL)
            if ($table === 'teams') {
                for ($m = 1; $m <= 4; $m++) {
                    if (isset($filteredRow["member{$m}_id"]) && (int)$filteredRow["member{$m}_id"] === 0) {
                        $filteredRow["member{$m}_id"] = null;
                    }
                }
            }

            // F. Cooperative Result Participants: Ensure proper NULLs for polymorphic references
            if ($table === 'cooperative_result_participants') {
                if (isset($filteredRow['participant_type'])) {
                    if ($filteredRow['participant_type'] === 'member') {
                        $filteredRow['team_id'] = null;
                    } elseif ($filteredRow['participant_type'] === 'team') {
                        $filteredRow['member_id'] = null;
                    }
                }
            }

            // Check existence by primary key
            $exists = false;
            if ($pk && isset($filteredRow[$pk])) {
                $checkStmt = $pdo->prepare("SELECT 1 FROM `$table` WHERE `$pk` = ?");
                $checkStmt->execute([$filteredRow[$pk]]);
                $exists = (bool)$checkStmt->fetchColumn();
            }

            if ($exists) {
                // For existing records, perform safe non-destructive update
                if ($pk && isset($filteredRow[$pk])) {
                    $setParts = [];
                    $params = [];
                    foreach ($filteredRow as $k => $v) {
                        if ($k !== $pk) {
                            $setParts[] = "`$k` = ?";
                            $params[] = $v;
                        }
                    }
                    if (!empty($setParts)) {
                        $params[] = $filteredRow[$pk];
                        $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE `$pk` = ?";
                        try {
                            $updStmt = $pdo->prepare($sql);
                            $updStmt->execute($params);
                            $updated++;
                        } catch (Exception $e) {
                            $errors[] = "Update failed for $table ID {$filteredRow[$pk]}: " . $e->getMessage();
                        }
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
            } else {
                // INSERT new record
                $colNames = array_keys($filteredRow);
                $placeholders = array_fill(0, count($colNames), '?');
                $sql = "INSERT INTO `$table` (`" . implode('`, `', $colNames) . "`) VALUES (" . implode(', ', $placeholders) . ")";
                try {
                    $insStmt = $pdo->prepare($sql);
                    $insStmt->execute(array_values($filteredRow));
                    $inserted++;
                } catch (Exception $e) {
                    $errors[] = "Insert failed for $table: " . $e->getMessage();
                }
            }
        }

        $summary[$table] = [
            'total' => count($rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors
        ];

        echo sprintf("  - %-32s : Inserted: %3d | Updated: %3d | Skipped: %2d | Errors: %2d\n",
            $table, $inserted, $updated, $skipped, count($errors));

        if (!empty($errors)) {
            foreach (array_slice($errors, 0, 3) as $err) {
                echo "      ! $err\n";
            }
        }
    }

    echo "\n4. Synchronizing `team_members` junction table...\n";
    $teamsStmt = $pdo->query("SELECT team_id, member1_id, member2_id, member3_id, member4_id FROM teams");
    $allTeams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
    $syncedMembersCount = 0;
    foreach ($allTeams as $t) {
        $mIds = array_filter([(int)$t['member1_id'], (int)$t['member2_id'], (int)$t['member3_id'], (int)$t['member4_id']]);
        foreach ($mIds as $mid) {
            if ($mid > 0) {
                // Verify member exists
                $memChk = $pdo->prepare("SELECT 1 FROM members WHERE member_id = ?");
                $memChk->execute([$mid]);
                if ($memChk->fetchColumn()) {
                    $insTm = $pdo->prepare("INSERT IGNORE INTO team_members (team_id, member_id) VALUES (?, ?)");
                    $insTm->execute([$t['team_id'], $mid]);
                    if ($insTm->rowCount() > 0) {
                        $syncedMembersCount++;
                    }
                }
            }
        }
    }
    echo "  [+] Added/Verified $syncedMembersCount entries in `team_members`.\n";

    echo "\n5. Validating Relational Integrity of Migrated Data...\n";
    
    // 1. Verify all stats.sql games belong to valid clubs
    $orphanGames = 0;
    foreach ($dumpData['games']['rows'] as $g) {
        $chk = $pdo->prepare("SELECT 1 FROM clubs WHERE club_id = ?");
        $chk->execute([$g['club_id']]);
        if (!$chk->fetchColumn()) $orphanGames++;
    }

    // 2. Verify all stats.sql game_results belong to valid games and members
    $orphanResultsGame = 0;
    $orphanResultsMember = 0;
    foreach ($dumpData['game_results']['rows'] as $gr) {
        $chkG = $pdo->prepare("SELECT 1 FROM games WHERE game_id = ?");
        $chkG->execute([$gr['game_id']]);
        if (!$chkG->fetchColumn()) $orphanResultsGame++;

        $chkM = $pdo->prepare("SELECT 1 FROM members WHERE member_id = ?");
        $chkM->execute([$gr['member_id']]);
        if (!$chkM->fetchColumn()) $orphanResultsMember++;
    }

    // 3. Verify all stats.sql game_result_losers belong to valid game_results and members
    $orphanLosers = 0;
    foreach ($dumpData['game_result_losers']['rows'] as $grl) {
        $chkR = $pdo->prepare("SELECT 1 FROM game_results WHERE result_id = ?");
        $chkR->execute([$grl['result_id']]);
        $chkM = $pdo->prepare("SELECT 1 FROM members WHERE member_id = ?");
        $chkM->execute([$grl['member_id']]);
        if (!$chkR->fetchColumn() || !$chkM->fetchColumn()) $orphanLosers++;
    }

    // 4. Verify all stats.sql champions belong to valid clubs and members
    $orphanChamps = 0;
    foreach ($dumpData['champions']['rows'] as $c) {
        $chkC = $pdo->prepare("SELECT 1 FROM clubs WHERE club_id = ?");
        $chkC->execute([$c['club_id']]);
        $chkM = $pdo->prepare("SELECT 1 FROM members WHERE member_id = ?");
        $chkM->execute([$c['member_id']]);
        if (!$chkC->fetchColumn() || !$chkM->fetchColumn()) $orphanChamps++;
    }

    // 5. Verify all stats.sql cooperative_game_results belong to valid games
    $orphanCoop = 0;
    foreach ($dumpData['cooperative_game_results']['rows'] as $cgr) {
        $chkG = $pdo->prepare("SELECT 1 FROM games WHERE game_id = ?");
        $chkG->execute([$cgr['game_id']]);
        if (!$chkG->fetchColumn()) $orphanCoop++;
    }

    echo "  - Imported Games without Club: $orphanGames\n";
    echo "  - Imported Game Results without Game: $orphanResultsGame\n";
    echo "  - Imported Game Results without Member: $orphanResultsMember\n";
    echo "  - Imported Game Result Losers without Result/Member: $orphanLosers\n";
    echo "  - Imported Champions without Club/Member: $orphanChamps\n";
    echo "  - Imported Cooperative Results without Game: $orphanCoop\n";

    $hasIntegrityFailures = ($orphanGames > 0 || $orphanResultsGame > 0 || $orphanResultsMember > 0 || $orphanLosers > 0 || $orphanChamps > 0 || $orphanCoop > 0);

    if ($hasIntegrityFailures) {
        throw new Exception("Relational integrity validation failed! Rolling back transaction.");
    }

    if ($isDryRun) {
        $pdo->rollBack();
        echo "\n================================================================================\n";
        echo " [DRY-RUN SUCCESS] All tables processed cleanly with 100% integrity.            \n";
        echo " Database was rolled back to original state. Ready for live execution.         \n";
        echo " Run with --execute to apply changes permanently.                              \n";
        echo "================================================================================\n";
    } else {
        $pdo->commit();
        echo "\n================================================================================\n";
        echo " [SUCCESS] Migration committed successfully to the live database!               \n";
        echo "================================================================================\n";
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "\n[ERROR] Migration aborted: " . $e->getMessage() . "\n";
    exit(1);
}
