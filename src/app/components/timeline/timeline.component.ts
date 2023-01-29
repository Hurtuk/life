import { Component, Input, OnInit } from '@angular/core';
import { Chapter } from 'src/app/models/chapter';
import { Range } from 'src/app/models/range';

@Component({
	selector: 'app-timeline',
	templateUrl: './timeline.component.html',
	styleUrls: ['./timeline.component.scss']
})
export class TimelineComponent implements OnInit {

	private MONTHS = ['jan', 'fév', 'mars', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'];

	@Input() data: {
		ranges: Range[];
		chapters: Chapter[];
	};
	@Input() displayRole?: Function;

	public maxLevel = 0;

	private startDate = new Date();
	private endDate = new Date();

	private days = 0;

	ngOnInit() {
		if (typeof this.displayRole === 'undefined') {
			this.displayRole = (range: Range) => range.title;
		}

		// Set min date
		for (const r of this.data.ranges) {
			if (r.startDate < this.startDate) {
				this.startDate = new Date(r.startDate);
			}
		}
		for (const c of this.data.chapters) {
			if (c.startDate < this.startDate) {
				this.startDate = new Date(c.startDate);
			}
		}
		this.startDate.setMonth(this.startDate.getMonth() - 6);
        this.startDate.setDate(1);

		this.endDate.setMonth(this.endDate.getMonth() + 6);

		// Calculate days to calculate percentages
		this.days = this.timeToDays(this.endDate.getTime() - this.startDate.getTime());

		this.calculateRangesLevels();
	}

	public getPercent(date: Date) {
		return this.timeToDays(date.getTime() - this.startDate.getTime()) * 100 / this.days;
	}

	public getWidth(range: Range): number {
		if (!range.endDate) {
			return 100 - this.getPercent(range.startDate);
		}
		return this.getPercent(range.endDate) - this.getPercent(range.startDate);
	}

	public getMonths(): { year: number, months: string[] }[] {
		const res = [];
		const current = new Date(this.startDate);
		let newYear;
		while (current.getTime() < this.endDate.getTime()) {
			// If this is a new year, push the year then create another one
			if (current.getFullYear() != newYear?.year) {
				if (newYear) {
					res.push(newYear);
				}
				newYear = { year: current.getFullYear(), months: [] };
			}
			// Fill the year
			newYear.months.push({
                name: this.MONTHS[current.getMonth()],
                days: this.getDaysOfMonth(current)
            });
			// Go to the next month
			current.setMonth(current.getMonth() + 1);
		}
		res.push(newYear);
		return res;
	}

	private timeToDays(time: number) {
		return time / 1000 / 3600 / 24;
	}

	private getDaysOfMonth(date: Date): number {
		return (new Date(date.getFullYear(), date.getMonth() + 1, 0)).getDate();
	}

	private calculateRangesLevels() {
		// Sort ranges from oldest to newest
		let ranges = this.data.ranges.sort((r1, r2) => r1.startDate.getTime() - r2.startDate.getTime());
		const levels = [ranges[0].endDate];
		ranges[0].level = 0;
		for (let i = 1; i < ranges.length; i++) {
			// Try on every level starting by the first one
			let level = 0;
			while (level < levels.length && (levels[level] > ranges[i].startDate || levels[level] === null)) {
				level++;
			}
			// Put it on the best level
			levels[level] = ranges[i].endDate;
			ranges[i].level = level;
			// Store the max level for the zone height
			this.maxLevel = Math.max(this.maxLevel, level);
		}
	}

	/**
	* Calculate brightness value by RGB or HEX color.
	* @param color (String) The color value in HEX (for example: 000000)
	* @returns (Number) The brightness value (dark) 0 ... 255 (light)
	*/
	public brightnessByColor(color: string): number {
		const m = color.match(/(\S{2})/g);
		const r = parseInt(m[0], 16),
			g = parseInt(m[1], 16),
			b = parseInt(m[2], 16);
		if (typeof r !== "undefined") {
			return ((r*299)+(g*587)+(b*114))/1000;
		}
		return 0;
	}
}
