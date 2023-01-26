import { Component } from '@angular/core';

@Component({
    selector: 'app-root',
    templateUrl: './app.component.html',
    styleUrls: ['./app.component.scss']
})
export class AppComponent {
    public data = {
        ranges: [
            { startDate: new Date(1999, 7, 3), endDate: new Date(2008, 2, 1) }
        ],
        chapters: [
            { startDate: new Date(1999, 7, 3), endDate: new Date(2008, 2, 1) }
        ]
    }
}
