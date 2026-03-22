# Sunday, March 22nd , 2026 - OOTP Baseball 27.1 Build 41
# 
# Dumping data for human_manager_history_financials.mysql.sql
# Game name: sMLB
# Game date: 1970-07-13
# 
DROP TABLE IF EXISTS `human_manager_history_financials`;
CREATE TABLE IF NOT EXISTS `human_manager_history_financials` (`human_manager_id` INT, `team_id` INT, `year` SMALLINT, `league_id` INT, `sub_league_id` INT, `division_id` INT, `gate_revenue` INT, `media_revenue` INT, `merchandising_revenue` INT, `other_revenue` INT, `revenue_sharing` INT, `luxury_sharing` INT, `playoff_revenue` INT, `cash` INT, `player_expenses` INT, `staff_expenses` INT, `stadium_expenses` INT, `attendance` INT, `fan_interest` SMALLINT, `fan_loyalty` SMALLINT, `fan_modifier` SMALLINT, `ticket_price` DOUBLE, `budget` INT, `market` SMALLINT, `owner_expectation` SMALLINT);

# 
# Dump completed
