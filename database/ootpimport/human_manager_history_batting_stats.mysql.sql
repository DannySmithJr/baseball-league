# Thursday, March 19th , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for human_manager_history_batting_stats.mysql.sql
# Game name: sMLB
# Game date: 1970-07-06
# 
DROP TABLE IF EXISTS `human_manager_history_batting_stats`;
CREATE TABLE IF NOT EXISTS `human_manager_history_batting_stats` (`human_manager_id` INT, `team_id` INT, `year` SMALLINT, `league_id` INT, `sub_league_id` INT, `division_id` INT, `level_id` SMALLINT, `split_id` SMALLINT, `pa` INT, `ab` INT, `h` INT, `k` INT, `tb` INT, `s` INT, `d` INT, `t` INT, `hr` INT, `sb` INT, `cs` INT, `rbi` INT, `r` INT, `bb` INT, `ibb` INT, `hp` INT, `sh` INT, `sf` INT, `ci` INT, `gdp` INT, `g` INT, `gs` INT, `ebh` INT, `pitches_seen` INT, `avg` DOUBLE, `obp` DOUBLE, `slg` DOUBLE, `rc` DOUBLE, `rc27` DOUBLE, `iso` DOUBLE, `tavg` DOUBLE, `woba` DOUBLE, `ops` DOUBLE, `sbp` DOUBLE);

# 
# Dump completed
