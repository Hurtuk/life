import { Component, OnInit } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { Chapter } from 'src/app/models/chapter';
import { Displayable } from 'src/app/models/displayable';
import { Range } from 'src/app/models/range';
import { LifeService } from 'src/app/services/life.service';

@Component({
  selector: 'app-timeline-page',
  templateUrl: './timeline-page.component.html',
  styleUrls: ['./timeline-page.component.scss']
})
export class TimelinePageComponent implements OnInit {

  private MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

  public DISPLAY = {
    'job': {
      title: 'Travail',
      roles: r => r.role + ((r.structure === 'Auto-entrepreneur') ? '' : ' chez ' + r.title)
    },
    'volunteering': {
      title: 'Bénévolat',
      roles: r => r.role + (r.title.match(/^[aeiouyhAEIOUYH]/) ? " de l'" : ' du ') + r.title
    },
    'hobby': {
      title: 'Activités',
      roles: r => (r.role === 'Elève' ?
                    'Cours d' + (r.title.match(/^[aeiouyhAEIOUYH]/) ? "'" : 'e ') + r.title.toLowerCase() :
                    r.title + (r.structure === 'Auto-entrepreneur' ? '' : ' (' + r.role + ')'))
    },
    'school': {
      title: 'Scolarité'
    },
    'love': {
      title: 'Romances'
    },
    'unclassified': {
      title: 'Divers'
    }
  };
  public display?: any;

  public type?: string;
  public data: { ranges: Range[], chapters: Chapter[] };

  public selected?: Displayable;

  constructor(
    private route: ActivatedRoute,
    private lifeService: LifeService
  ) {}

  ngOnInit() {
    this.route.params.subscribe(params => {
      delete this.data;
      this.type = params.type;
      this.display = this.DISPLAY[this.type];
      this.lifeService.getData({ type: this.type }).subscribe(data => {
        this.data = data;
      })
    })
  }

  public getSelectedDates(): string {
    if (!this.selected) return "";
    
    const start = this.selected.startDate,
      end = this.selected.endDate;
    
    if (start.getTime() == end.getTime()) {
      return 'Le ' + this.longDate(start);
    }

    return 'Du ' + this.longDate(start, start.getMonth() != end.getMonth(), start.getFullYear() != end.getFullYear()) + ' au ' + this.longDate(end);
  }

  private longDate(d: Date, month = true, year = true): string {
    return d.getDate() + (month ? ' ' + this.MONTHS[d.getMonth()] : '') + (year ? ' ' + d.getFullYear() : '');
  }
}
