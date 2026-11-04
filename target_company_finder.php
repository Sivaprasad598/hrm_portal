<?php
/*
 * Target Company Finder (Lead Finder). The page is part of the portal now: Leads → Lead Generation Bot
 * (LeadGenerationBotController, view lead-generation-bot/target-company-finder.php), behind the portal login and
 * for administrators only. This file only sends the browser there.
 */
header('Location: public/account/lead-generation-bot', true, 302);
exit;
