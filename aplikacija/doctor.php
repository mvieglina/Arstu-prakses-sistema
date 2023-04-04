<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8"/>
  <title>Ārsta prakses pieraksta sistēma</title>

  <link type="text/css" rel="stylesheet" href="css/layout.css"/>

  
  
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
      <div class="space">
        <select id="doctor" name="doctor"></select>
      </div>
      <div id="calendar"></div>
    </div>

  </div>
</div>

<script src="js/daypilot/daypilot-all.min.js"></script>

<script>
  const elements = {
    doctor: document.querySelector("#doctor")
  };

  const nav = new DayPilot.Navigator("nav");
  nav.selectMode = "week";
  nav.showMonths = 3;
  nav.skipMonths = 3;
  nav.onTimeRangeSelected = args => {
    loadEvents(args.start.firstDayOfWeek(), args.start.addDays(7));
  };
  nav.init();

  const calendar = new DayPilot.Calendar("calendar");
  calendar.viewType = "Week";
  calendar.timeRangeSelectedHandling = "Disabled";
  calendar.eventDeleteHandling = "Update";

  calendar.onEventMoved = async args => {
    const {data} = await DayPilot.Http.post("backend_move.php", args);
    calendar.message(data.message);
  };
  calendar.onEventResized = async args => {
    const {data} = await DayPilot.Http.post("backend_move.php", args);
    calendar.message(data.message);
  };
  calendar.onEventDeleted = async args => {
    const params = {
      id: args.e.id(),
    };
    await DayPilot.Http.post("backend_delete.php", params);
    calendar.message("Deleted.");
  };
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

  calendar.onEventClick = async args => {

    const form = [
      {name: "Edit Appointment"},
      {name: "Name", id: "text"},
      {name: "Status", id: "tags.status", options: [
          {name: "Free", id: "free"},
          {name: "Waiting", id: "waiting"},
          {name: "Confirmed", id: "confirmed"},
        ]},
      {name: "From", id: "start", dateFormat: "MMMM d, yyyy h:mm tt", disabled: true},
      {name: "To", id: "end", dateFormat: "MMMM d, yyyy h:mm tt", disabled: true},
      {name: "Doctor", id: "resource", disabled: true, options: doctors},
    ];

    const data = args.e.data;

    const options = {
      focus: "text"
    };

    const modal = await DayPilot.Modal.form(form, data, options);
    if (modal.canceled) {
      return;
    }

    const params = {
      id: modal.result.id,
      name: modal.result.text,
      status: modal.result.tags.status
    };

    await DayPilot.Http.post("backend_update.php", params);
    calendar.events.update(modal.result);

  };
  calendar.init();

  async function loadEvents(day) {
    const start = nav.visibleStart();
    const end = nav.visibleEnd();

    const params = {
      doctor: elements.doctor.value,
      start: start.toString(),
      end: end.toString()
    };

    const {data} = await DayPilot.Http.post("backend_events_doctor.php", params);

    if (day) {
      calendar.startDate = day;
    }
    calendar.events.list = data;
    calendar.update();

    nav.events.list = data;
    nav.update();
  }

  elements.doctor.addEventListener("change", () => {
    loadEvents();
  });

  let doctors = [];

  async function init() {
    const {data} = await DayPilot.Http.get("backend_resources.php");

    doctors = data;

    doctors.forEach(item => {
      const option = document.createElement("option");
      option.value = item.id;
      option.innerText = item.name;
      elements.doctor.appendChild(option);
    });
  }

  init();
  loadEvents();

</script>

</body>
</html>
