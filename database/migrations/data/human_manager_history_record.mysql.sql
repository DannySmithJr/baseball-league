# Sunday, March 22nd , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for human_manager_history_record.mysql.sql
# Game name: sMLB
# Game date: 1970-07-20
# 
DROP TABLE IF EXISTS `human_manager_history_record`;
CREATE TABLE IF NOT EXISTS `human_manager_history_record` (`human_manager_id` INT, `team_id` INT, `year` SMALLINT, `league_id` INT, `sub_league_id` INT, `division_id` INT, `g` SMALLINT, `w` SMALLINT, `l` SMALLINT, `pos` SMALLINT, `pct` DOUBLE, `gb` DOUBLE, `streak` SMALLINT, `magic_number` SMALLINT);

# 
# Dump completed
