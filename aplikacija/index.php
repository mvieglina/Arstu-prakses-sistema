<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>HTML5 Doctor Appointment Scheduling (JavaScript/PHP)</title>

  <link type="text/css" rel="stylesheet" href="css/layout.css"/>

  <!-- DayPilot library -->
  <script src="js/daypilot/daypilot-all.min.js"></script>

</head>
<body>
<?php require_once '_header.php'; ?>

<div class="main">
  <?php require_once '_navigation.php'; ?>

  <div>

    <div class="column-left">
      <div id="nav"></div>
    </div>
    <div class="column-main">
      <div class="toolbar">Available time slots:</div>
      <div id="calendar"></div>
    </div>

  </div>
</div>

<script src="js/daypilot/daypilot-all.min.js"></script>

<script>
  const nav = new DayPilot.Navigator("nav");
  nav.selectMode = "week";
  nav.showMonths = 3;
  nav.skipMonths = 3;
  nav.onTimeRangeSelected = args => {
    loadEvents(args.start.firstDayOfWeek(DayPilot.Locale.find(nav.locale).weekStarts), args.start.addDays(7));
  };
  nav.init();

  const calendar = new DayPilot.Calendar("calendar");
  calendar.viewType = "Week";
  calendar.timeRangeSelectedHandling = "Disabled";
  calendar.eventMoveHandling = "Disabled";
  calendar.eventResizeHandling = "Disabled";
  calendar.eventArrangement = "SideBySide";
  calendar.onBeforeEventRender = args => {
    if (!args.data.tags) {
      return;
    }
    switch (args.data.tags.status) {
      case "free":
        args.data.backColor = "#3d85c6";  // blue
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        args.data.html = "Available<br/>" + args.data.tags.doctor;
        args.data.toolTip = "Click to request this time slot";
        break;
      case "waiting":
        args.data.backColor = "#e69138";  // orange
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        args.data.html = "Your appointment, waiting for confirmation";
        break;
      case "confirmed":
        args.data.backColor = "#6aa84f";  // green
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        args.data.html = "Your appointment, confirmed";
        break;
    }
  };
  calendar.onEventClick = async args => {
    if (args.e.tag("status") !== "free") {
      calendar.message("You can only request a new appointment in a free slot.");
      return;
    }

    const form = [
      {name: "Request an Appointment"},
      {name: "From", id: "start", dateFormat: "MMMM d, yyyy h:mm tt", disabled: true},
      {name: "To", id: "end", dateFormat: "MMMM d, yyyy h:mm tt", disabled: true},
      {name: "Name", id: "name"},
    ];

    const data = {
      id: args.e.id(),
      start: args.e.start(),
      end: args.e.end(),
    };

    const options = {
      focus: "name"
    };

    const modal = await DayPilot.Modal.form(form, data, options);
    if (modal.canceled) {
      return;
    }

    await DayPilot.Http.post("backend_request_save.php", modal.result);

    args.e.data.tags.status = "waiting";
    calendar.events.update(args.e.data);

  };
  calendar.init();

  loadEvents();

  async function loadEvents(day) {
    const start = nav.visibleStart() > new DayPilot.Date() ? nav.visibleStart() : new DayPilot.Date();

    const params = {
      start: start.toString(),
      end: nav.visibleEnd().toString()
    };

    const {data} = await DayPilot.Http.post("backend_events_free.php", params);

    if (day) {
      calendar.startDate = day;
    }
    calendar.events.list = data;
    calendar.update();

    nav.events.list = data;
    nav.update();
  }
</script>

</body>
</html>
