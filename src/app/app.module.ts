import { HttpClientModule } from '@angular/common/http';
import { NgModule } from '@angular/core';
import { BrowserModule } from '@angular/platform-browser';
import { httpInterceptorProviders } from 'src/interceptors';

import { AppRoutingModule } from './app-routing.module';
import { AppComponent } from './app.component';
import { LoveStoriesComponent } from './components/love-stories/love-stories.component';
import { TimelineComponent } from './components/timeline/timeline.component';
import { TimelinePageComponent } from './components/timeline-page/timeline-page.component';
import { HomePageComponent } from './components/home-page/home-page.component';
import { TextFormatPipe } from './pipes/text-format.pipe';
import { InsertPicsPipe } from './pipes/insert-pics.pipe';

@NgModule({
  declarations: [
    AppComponent,
    LoveStoriesComponent,
    TimelineComponent,
    TimelinePageComponent,
    HomePageComponent,
    TextFormatPipe,
    InsertPicsPipe
  ],
  imports: [
    BrowserModule,
    AppRoutingModule,
    HttpClientModule
  ],
  providers: [httpInterceptorProviders],
  bootstrap: [AppComponent]
})
export class AppModule { }
