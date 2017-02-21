<?php

class PluginEscaladeNotification {
   const NTRGT_TICKET_REQUESTER_USER          = 357951;
   const NTRGT_TICKET_REQUESTER_GROUP         = 357952;
   const NTRGT_TICKET_REQUESTER_GROUP_MANAGER = 357953;
   const NTRGT_TICKET_WATCH_USER              = 357954;
   const NTRGT_TICKET_WATCH_GROUP             = 357955;
   const NTRGT_TICKET_WATCH_GROUP_MANAGER     = 357956;
   const NTRGT_TICKET_TECH_GROUP              = 357957;
   const NTRGT_TICKET_TECH_USER               = 357958;
   const NTRGT_TICKET_TECH_GROUP_MANAGER      = 357959;
   const NTRGT_TASK_GROUP                     = 357960;

   static function addTargets(NotificationTarget $target) {
      if ($target instanceof NotificationTargetPlanningRecall) {
         // add new targets to planning recall notification
         $target->addTarget(self::NTRGT_TICKET_REQUESTER_USER,
            __('Requester user of the ticket', 'escalade'));
         $target->addTarget(self::NTRGT_TICKET_REQUESTER_GROUP,
            __('Requester group'));
         $target->addTarget(self::NTRGT_TICKET_REQUESTER_GROUP_MANAGER,
            __('Requester group manager'));
         $target->addTarget(self::NTRGT_TICKET_WATCH_USER,
            __('Watcher user'));
         $target->addTarget(self::NTRGT_TICKET_WATCH_GROUP,
            __('Watcher group'));
         $target->addTarget(self::NTRGT_TICKET_WATCH_GROUP_MANAGER,
            __('Watcher group manager'));
         $target->addTarget(self::NTRGT_TICKET_TECH_GROUP,
            __('Group in charge of the ticket'));
         $target->addTarget(self::NTRGT_TICKET_TECH_USER,
            __('Technician in charge of the ticket'));
         $target->addTarget(self::NTRGT_TICKET_TECH_GROUP_MANAGER,
            __('Manager of the group in charge of the ticket'));
         $target->addTarget(self::NTRGT_TASK_GROUP,
            __('Group in charge of the task'));

         // change label for this core target to avoid confusion with NTRGT_TICKET_REQUESTER_USER
         $target->addTarget(Notification::AUTHOR,
            __('Requester user of the task/reminder', 'escalade'));
      }
   }

   static function getActionTargets(NotificationTarget $target) {
      if ($target instanceof NotificationTargetPlanningRecall) {
         $item = new $target->obj->fields['itemtype'];
         $item->getFromDB($target->obj->fields['items_id']);
         if ($item instanceof TicketTask) {

            $ticket = new Ticket;
            $ticket->getFromDB($item->getField('tickets_id'));

            switch ($target->data['items_id']) {
               case self::NTRGT_TICKET_REQUESTER_GROUP:
                  $manager = 0;
               case self::NTRGT_TICKET_REQUESTER_GROUP_MANAGER:
                  if (!isset($manager)) {
                     $manager = 1;
                  }
                  self::addGroupsOfTicket($ticket->getID(), $manager, CommonITILActor::REQUESTER);
                  break;

               case self::NTRGT_TICKET_WATCH_GROUP:
                  $manager = 0;
               case self::NTRGT_TICKET_WATCH_GROUP_MANAGER:
                  if (!isset($manager)) {
                     $manager = 1;
                  }
                  self::addGroupsOfTicket($ticket->getID(), $manager, CommonITILActor::OBSERVER);
                  break;

               case self::NTRGT_TICKET_TECH_GROUP:
                  $manager = 0;
               case self::NTRGT_TICKET_TECH_GROUP_MANAGER:
                  if (!isset($manager)) {
                     $manager = 1;
                  }
                  self::addGroupsOfTicket($ticket->getID(), $manager, CommonITILActor::ASSIGN);
                  break;

               case self::NTRGT_TICKET_REQUESTER_USER:
                  $user_type = CommonITILActor::REQUESTER;
               case self::NTRGT_TICKET_WATCH_USER:
                  $user_type = CommonITILActor::OBSERVER;
               case self::NTRGT_TICKET_TECH_USER:
                  if (!isset($user_type)) {
                     $user_type = CommonITILActor::ASSIGN;
                  }
                  self::addUsersOfTicket($ticket->getID(), $user_type);
                  break;

               case self::NTRGT_TASK_GROUP:
                  $target->getAddressesByGroup(0, $item->getField('groups_id_tech'));
                  break;
            }
         }
      }
   }

   static function addGroupsOfTicket($tickets_id = 0, $manager = 0, $type = CommonITILActor::REQUESTER) {
      $group_ticket = new Group_Ticket;
      foreach($group_ticket->find("`tickets_id` = $tickets_id AND `type` = $type") as $current) {
         $target->getAddressesByGroup($manager, $current['groups_id']);
      }
   }

   static function addUsersOfTicket($tickets_id = 0, $type = CommonITILActor::REQUESTER) {
      $ticket_user = new Ticket_User;
      $user        = new User;
      foreach($ticket_user->find("`type` = $type
                                  AND `tickets_id` = $tickets_id") as $current) {
         if ($user->getFromDB($current['users_id'])) {
            $target->addToAddressesList(['language' => $user->getField('language'),
                                         'users_id' => $user->getField('id')]);
         }
      }
   }
}