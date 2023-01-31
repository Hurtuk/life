import { Component, OnInit } from '@angular/core';
import { ActivatedRoute, ParamMap } from '@angular/router';
import { Chapter } from 'src/app/models/chapter';
import { Range } from 'src/app/models/range';
import { LifeService } from 'src/app/services/life.service';

@Component({
  selector: 'app-timeline-page',
  templateUrl: './timeline-page.component.html',
  styleUrls: ['./timeline-page.component.scss']
})
export class TimelinePageComponent implements OnInit {

  public DISPLAY_ROLES = {
    'work': r => r.role + (r.structure === 'Auto-entrepreneur') ? '' : ' chez ' + r.title,
    'volunteering': r => r.role + (r.title.match(/^[aeiouyhAEIOUYH]/) ? " de l'" : ' du ') + r.title,
    'hobby': r => (r.role === 'Elève' ?
                    'Cours d' + (r.title.match(/^[aeiouyhAEIOUYH]/) ? "'" : 'e ') + r.title.toLowerCase() :
                    r.title + (r.structure === 'Auto-entrepreneur' ? '' : ' (' + r.role + ')'))
  };
  public displayRoles;

  public type?: string;
  public data: { ranges: Range[], chapters: Chapter[] };

  constructor(
    private route: ActivatedRoute,
    private lifeService: LifeService
  ) {}

  ngOnInit() {
    this.route.params.subscribe(params => {
      delete this.data;
      this.type = params.type;
      this.displayRoles = this.DISPLAY_ROLES[this.type];
      this.lifeService.getData({ type: this.type }).subscribe(data => {
        this.data = data;
      })
    })
  }
}
