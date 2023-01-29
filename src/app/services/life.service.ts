import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { HttpClient } from '@angular/common/http';
import { Chapter } from '../models/chapter';
import { Range } from '../models/range';

@Injectable({
  providedIn: 'root'
})
export class LifeService {

  private URL = 'http://louiecinephile.fr/lifeBO/api/';

  constructor(
    private http: HttpClient
  ) { }

  public getData(filters?: {[key: string]: string}): Observable<{ranges: Range[], chapters: Chapter[]}> {
    return this.http.get<{ranges: Range[], chapters: Chapter[]}>(this.URL + 'getData.php', { params: filters });
  }
}
