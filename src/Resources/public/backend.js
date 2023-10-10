// Select the badge element
var badge = document.querySelector('#header .badge-title');

// Get the current badge title
var title = badge.innerHTML;

// Case: Live
if(title.includes("Live")){

    // Add class badge-title--live to badge
    badge.classList.add('badge-title--live');
}

// Case: Stage
if(title.includes("Stage")){

    // Add class badge-title--stage to badge
    badge.classList.add('badge-title--stage');
}

// Case: Local
if(title.includes("Local")){

    // Add class badge-title--local to badge
    badge.classList.add('badge-title--local');
}

// Case: Content Freeze
if(title.includes("Freeze")){

    // Add class badge-title--freeze to badge
    badge.classList.add('badge-title--freeze');
}

// Get the current app-title
var appTitle = document.querySelector('#header .app-title');

// Get the current app-title text
var appTitleText = appTitle.innerHTML;

// Get the data-backend-title attribute from body
var backendTitle = document.querySelector('body').getAttribute('data-backend-title');

// Check if the backendTitle is not empty
if(backendTitle !== null){

    // Replace the app-title text with the backendTitle
    appTitle.innerHTML = appTitleText + ' | ' + backendTitle;
}
