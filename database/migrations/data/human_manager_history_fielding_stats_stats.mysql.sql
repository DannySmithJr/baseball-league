# Sunday, March 22nd , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for human_manager_history_fielding_stats_stats.mysql.sql
# Game name: sMLB
# Game date: 1970-07-13
# 
DROP TABLE IF EXISTS `human_manager_history_fielding_stats_stats`;
CREATE TABLE IF NOT EXISTS `human_manager_history_fielding_stats_stats` (`human_manager_id` INT, `team_id` INT, `year` SMALLINT, `league_id` INT, `sub_league_id` INT, `division_id` INT, `level_id` SMALLINT, `split_id` SMALLINT, `position` SMALLINT, `g` INT, `gs` INT, `tc` INT, `a` INT, `po` INT, `e` INT, `dp` INT, `tp` INT, `pb` INT, `sba` INT, `rto` INT, `er` INT, `ip` INT, `ipf` INT, `pct` DOUBLE, `range` DOUBLE, `rtop` DOUBLE, `cera` DOUBLE);

# 
# Dump completed
