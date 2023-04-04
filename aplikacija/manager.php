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

      <div class="toolbar">
                    <span class="toolbar-item">Scale:
<!--                        <label for='scale-15min'><input type="radio" value="15min" name="scale" id='scale-15min'> 15-Min</label>-->
                        <label for='scale-hours'><input type="radio" value="hours" name="scale" id='scale-hours'
                                                        checked> Hours</label>
                        <label for='scale-shifts'><input type="radio" value="shifts" name="scale" id='scale-shifts'> Shifts</label></span>
        <span class="toolbar-item"><label for="business-only"><input type="checkbox" id="business-only"> Hide non-business hours</label></span>
        <span class="toolbar-item">Slots: <button id="clear">Clear</button> Deletes all free slots this month</span>

      </div>

      <div id="scheduler"></div>
    </div>

  </div>
</div>

<script src="js/daypilot/daypilot-all.min.js"></script>

<script>
  const nav = new DayPilot.Navigator("nav");
  nav.selectMode = "month";
  nav.showMonths = 3;
  nav.skipMonths = 3;
  nav.onTimeRangeSelected = args => {
    if (scheduler.visibleStart().getDatePart() <= args.day && args.day < scheduler.visibleEnd()) {
      scheduler.scrollTo(args.day, "fast");  // just scroll
    } else {
      loadEvents(args.day);  // reload and scroll
    }
  };
  nav.init();

  const scheduler = new DayPilot.Scheduler("scheduler");
  scheduler.visible = false; // will be displayed after loading the resources
  scheduler.scale = "Manual";
  scheduler.timeline = getTimeline();
  scheduler.timeHeaders = getTimeHeaders();
  scheduler.useEventBoxes = "Never";
  scheduler.eventDeleteHandling = "Update";
  scheduler.eventClickHandling = "Disabled";
  scheduler.eventMoveHandling = "Disabled";
  scheduler.eventResizeHandling = "Disabled";
  scheduler.allowEventOverlap = false;
  scheduler.onBeforeTimeHeaderRender = args => {
    args.header.text = args.header.text.replace(" AM", "a").replace(" PM", "p");  // shorten the hour header
  };
  scheduler.onBeforeEventRender = args => {
    switch (args.data.tags.status) {
      case "free":
        args.data.backColor = "#3d85c6";  // blue
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        args.data.deleteDisabled = document.querySelector("input[name=scale]:checked").value === "shifts"; // only allow deleting in the more detailed hour scale mode
        break;
      case "waiting":
        args.data.backColor = "#e69138";  // orange
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        break;
      case "confirmed":
        args.data.backColor = "#6aa84f";  // green
        args.data.barHidden = true;
        args.data.borderColor = "darker";
        args.data.fontColor = "white";
        break;
    }
  };

  scheduler.onEventDeleted = async args => {
    var params = {
      id: args.e.id(),
    };
    const {data: result} = await DayPilot.Http.post("backend_delete.php", params);
    scheduler.message("Deleted.");
  };

  scheduler.onTimeRangeSelected = async args => {
    const dp = scheduler;
    const scale = document.querySelector("input[name=scale]:checked").value;

    const params = {
      start: args.start.toString(),
      end: args.end.toString(),
      resource: args.resource,
      scale: scale
    };

    dp.clearSelection();

    const {data} = await DayPilot.Http.post("backend_create.php", params);
    loadEvents();
    dp.message(data.message);

  };
  scheduler.init();


  loadResources();
  loadEvents(DayPilot.Date.today());

  async function loadEvents(day) {
    let from = scheduler.visibleStart();
    let to = scheduler.visibleEnd();
    if (day) {
      from = new DayPilot.Date(day).firstDayOfMonth();
      to = from.addMonths(1);
    }

    const params = {
      start: from.toString(),
      end: to.toString()
    };

    const {data} = await DayPilot.Http.post("backend_events.php", params);

    const options = {
      events: data
    };

    if (day) {
      options.timeline = getTimeline(day);
      options.scrollTo = day;
    }

    scheduler.update(options);

    nav.events.list = data;
    nav.update();

  }

  async function loadResources() {
    const {data} = await DayPilot.Http.get("backend_resources.php");
    scheduler.update({
      resources: data,
      visible: true
    });
  }

  function getTimeline(date) {
    date = date || DayPilot.Date.today();
    const start = new DayPilot.Date(date).firstDayOfMonth();
    const days = start.daysInMonth();
    const scale = document.querySelector("input[name=scale]:checked").value;
    const businessOnly = document.querySelector("#business-only").checked;

    let morningShiftStarts = 9;
    let morningShiftEnds = 13;
    let afternoonShiftStarts = 14;
    let afternoonShiftEnds = 18;

    if (!businessOnly) {
      morningShiftStarts = 0;
      morningShiftEnds = 12;
      afternoonShiftStarts = 12;
      afternoonShiftEnds = 24;
    }

    const timeline = [];

    let increaseMorning;  // in hours
    let increaseAfternoon;  // in hours
    switch (scale) {
      case "15min":
        increaseMorning = 0.25;
        increaseAfternoon = 0.25;
        break;
      case "hours":
        increaseMorning = 1;
        increaseAfternoon = 1;
        break;
      case "shifts":
        increaseMorning = morningShiftEnds - morningShiftStarts;
        increaseAfternoon = afternoonShiftEnds - afternoonShiftStarts;
        break;
      default:
        throw "Invalid scale value";
    }

    for (let i = 0; i < days; i++) {
      const day = start.addDays(i);

      for (let x = morningShiftStarts; x < morningShiftEnds; x += increaseMorning) {
        timeline.push({start: day.addHours(x), end: day.addHours(x + increaseMorning)});
      }
      for (let x = afternoonShiftStarts; x < afternoonShiftEnds; x += increaseAfternoon) {
        timeline.push({start: day.addHours(x), end: day.addHours(x + increaseAfternoon)});
      }
    }

    return timeline;
  }

  function getTimeHeaders() {
    const scale = document.querySelector('input[name=scale]:checked').value;
    switch (scale) {
      case "15min":
        return [
          {groupBy: "Month"},
          {groupBy: "Day", format: "dddd d"},
          {groupBy: "Hour", format: "h tt"},
          {groupBy: "Cell", format: "m"}
        ];
        break;
      case "hours":
        return [
          {groupBy: "Month"},
          {groupBy: "Day", format: "dddd d"},
          {groupBy: "Hour", format: "h tt"}
        ];
        break;
      case "shifts":
        return [
          {groupBy: "Month"},
          {groupBy: "Day", format: "dddd d"},
          {groupBy: "Cell", format: "tt"}
        ];
        break;
    }
  }


  document.querySelector("#business-only").addEventListener("click", () => {
    scheduler.timeline = getTimeline();
    scheduler.update();
  });

  const radios = Array.apply(null, document.querySelectorAll("input[name=scale]")).forEach(item => {
    item.addEventListener("change", ev => {
      scheduler.timeline = getTimeline();
      scheduler.timeHeaders = getTimeHeaders();
      scheduler.update();
    });
  });

  document.querySelector("#clear").addEventListener("click", async () => {
    const dp = scheduler;
    const params = {
      start: dp.visibleStart(),
      end: dp.visibleEnd()
    };

    const {data} = await DayPilot.Http.post("backend_clear.php", params);
    dp.message(data.message);
    loadEvents();
  });

</script>

</body>
</html>
