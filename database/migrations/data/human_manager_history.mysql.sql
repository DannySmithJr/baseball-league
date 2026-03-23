# Sunday, March 22nd , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for human_manager_history.mysql.sql
# Game name: sMLB
# Game date: 1970-07-20
# 
DROP TABLE IF EXISTS `human_manager_history`;
CREATE TABLE IF NOT EXISTS `human_manager_history` (`human_manager_id` INT, `team_id` INT, `year` SMALLINT, `league_id` INT, `sub_league_id` INT, `division_id` INT, `best_hitter_id` INT, `best_pitcher_id` INT, `best_rookie_id` INT, `manager_id` INT, `made_playoffs` TINYINT, `won_playoffs` TINYINT, `fired` TINYINT, `position_in_division` SMALLINT);

# 
# Dump completed
