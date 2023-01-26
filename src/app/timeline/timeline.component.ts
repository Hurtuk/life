import { Component, Input, OnInit } from '@angular/core';

class TimelineData {
	public ranges: any[];
	public chapters: any[];
}

@Component({
	selector: 'app-timeline',
	templateUrl: './timeline.component.html',
	styleUrls: ['./timeline.component.scss']
})
export class TimelineComponent implements OnInit {

	private MONTHS = ['jan', 'fév', 'mars', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'];

	@Input() data: TimelineData;

	private startDate = new Date();
	private endDate = new Date();

	private days = 0;

	ngOnInit() {
		// Set min date
		for (const r of this.data.ranges) {
			if (r.startDate < this.startDate) {
				this.startDate = r.startDate;
			}
		}
		this.startDate.setMonth(this.startDate.getMonth() - 6);
        this.startDate.setDate(1);

		// Calculate days to calculate percentages
		this.days = this.timeToDays(this.endDate.getTime()) - this.timeToDays(this.startDate.getTime());
	}

	public getPercent(date: Date) {
		return this.timeToDays(date.getTime()) - this.timeToDays(this.startDate.getTime()) * 100 / this.days;
	}

	public getMonths(): { year: number, months: string[] }[] {
		const res = [];
		const current = new Date(this.startDate);
		let newYear;
		while (current.getTime() < this.endDate.getTime()) {
			if (current.getFullYear() != newYear?.year) {
				if (newYear) {
					res.push(newYear);
				}
				newYear = { year: current.getFullYear(), months: [] };
			}
			newYear.months.push({
                name: this.MONTHS[current.getMonth()],
                days: current.getDate()
            });
			current.setMonth(current.getMonth() + 1);
		}
		res.push(newYear);
        console.log(res);
		return res;
	}

	private timeToDays(time: number) {
		return time / 1000 / 3600 / 24;
	}
}
